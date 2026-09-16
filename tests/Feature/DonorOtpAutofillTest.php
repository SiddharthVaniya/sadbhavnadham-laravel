<?php

use App\Mail\DonorOtpMail;
use App\Models\AisensyAccount;
use App\Models\Donor;
use App\Models\Setting;
use App\Support\DonorOtpService;
use App\Support\DonorPortalSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('tells new numbers that no saved profile exists', function () {
    $response = $this->postJson(route('donate.otp.send'), [
        'login_method' => 'phone',
        'donor_phone' => '9876543210',
        'phone_dial_code' => '91',
        'donor_country_code' => 'IN',
    ]);

    $response->assertOk()
        ->assertJson([
            'found' => false,
        ]);
});

it('sends an email otp for a returning donor and starts a portal session after verify', function () {
    Mail::fake();

    $donor = Donor::factory()->create([
        'name' => 'મુકેશભાઈ પટેલ',
        'email' => 'mukesh@example.com',
        'phone' => '9876543210',
        'address' => 'Surat',
        'pincode' => '395006',
        'city' => 'Surat',
        'state' => 'Gujarat',
        'country' => 'INDIA',
        'pan_number' => 'ABCDE1234F',
        'date_of_birth' => '1987-05-14',
    ]);

    $send = $this->postJson(route('donate.otp.send'), [
        'donor_phone' => '9876543210',
        'phone_dial_code' => '91',
        'donor_country_code' => 'IN',
    ]);

    $send->assertOk()
        ->assertJson([
            'found' => true,
            'sent' => true,
            'channel' => 'email',
        ]);

    Mail::assertSent(DonorOtpMail::class, function (DonorOtpMail $mail) use ($donor) {
        return $mail->hasTo($donor->email)
            && strlen($mail->otp) === 6;
    });

    $mail = null;
    Mail::assertSent(DonorOtpMail::class, function (DonorOtpMail $sent) use (&$mail) {
        $mail = $sent;

        return true;
    });

    $verify = $this->postJson(route('donate.otp.verify'), [
        'donor_phone' => '9876543210',
        'phone_dial_code' => '91',
        'donor_country_code' => 'IN',
        'otp' => $mail->otp,
    ]);

    $verify->assertOk()
        ->assertJson([
            'verified' => true,
            'signed_in' => true,
            'display_name' => 'મુકેશભાઈ',
            'profile' => [
                'donor_name' => 'મુકેશભાઈ પટેલ',
                'donor_email' => 'mukesh@example.com',
                'donor_phone' => '9876543210',
                'address' => 'Surat',
                'pincode' => '395006',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'pan_number' => 'ABCDE1234F',
                'date_of_birth' => '1987-05-14',
            ],
        ]);

    $this->getJson(route('donate.otp.session'))
        ->assertOk()
        ->assertJson([
            'signed_in' => true,
            'display_name' => 'મુકેશભાઈ',
        ]);

    expect(app(DonorPortalSession::class)->donorId())->toBe($donor->id);
});

it('finds an international donor using dial code and national number', function () {
    Mail::fake();

    Donor::factory()->create([
        'name' => 'Sanjay Morzaria',
        'email' => 'sanjay@example.co.uk',
        'phone' => '447932623852',
        'country' => 'UNITED KINGDOM',
        'country_code' => 'GB',
    ]);

    $this->postJson(route('donate.otp.send'), [
        'donor_phone' => '7932623852',
        'phone_dial_code' => '44',
        'donor_country_code' => 'GB',
    ])->assertOk()
        ->assertJson([
            'found' => true,
            'sent' => true,
            'channel' => 'email',
        ]);
});

it('rejects an incorrect otp', function () {
    Cache::put('donate_otp:9876543210', [
        'hash' => Hash::make('123456'),
        'attempts' => 0,
        'donor_id' => Donor::factory()->create([
            'phone' => '9876543210',
            'email' => 'donor@example.com',
        ])->id,
    ], 300);

    $this->postJson(route('donate.otp.verify'), [
        'donor_phone' => '9876543210',
        'phone_dial_code' => '91',
        'otp' => '000000',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['otp']);
});

it('does not return profile before verification', function () {
    Mail::fake();

    Donor::factory()->create([
        'phone' => '9123456780',
        'email' => 'secret@example.com',
        'name' => 'Secret Donor',
        'pan_number' => 'ABCDE1234F',
    ]);

    $this->postJson(route('donate.otp.send'), [
        'donor_phone' => '9123456780',
        'phone_dial_code' => '91',
    ])->assertOk()
        ->assertJsonMissing([
            'profile' => [
                'pan_number' => 'ABCDE1234F',
            ],
        ])
        ->assertJsonMissingPath('profile');
});

it('builds a profile payload from the donor model', function () {
    $donor = Donor::factory()->create([
        'name' => 'Asha Patel',
        'email' => 'asha@example.com',
        'phone' => '9988776655',
    ]);

    $payload = app(DonorOtpService::class)->profilePayload($donor);

    expect($payload['donor_name'])->toBe('Asha Patel')
        ->and($payload['donor_email'])->toBe('asha@example.com')
        ->and($payload['donor_phone'])->toBe('9988776655');
});

it('clears the donor portal session on logout', function () {
    $donor = Donor::factory()->create([
        'name' => 'Asha Patel',
        'phone' => '9988776655',
    ]);

    app(DonorPortalSession::class)->login($donor);

    $this->postJson(route('donate.otp.logout'))
        ->assertOk()
        ->assertJson([
            'signed_in' => false,
        ]);

    $this->getJson(route('donate.otp.session'))
        ->assertOk()
        ->assertJson([
            'signed_in' => false,
        ]);
});

it('returns a clear cooldown when resending too quickly', function () {
    Mail::fake();

    Donor::factory()->create([
        'phone' => '9000012345',
        'email' => 'cooldown@example.com',
    ]);

    $this->postJson(route('donate.otp.send'), [
        'donor_phone' => '9000012345',
        'phone_dial_code' => '91',
    ])->assertOk();

    $this->postJson(route('donate.otp.send'), [
        'donor_phone' => '9000012345',
        'phone_dial_code' => '91',
    ])->assertUnprocessable()
        ->assertJsonPath('sent', false)
        ->assertJsonStructure(['cooldown_seconds']);
});

it('signs in a returning donor with email otp', function () {
    Mail::fake();

    $donor = Donor::factory()->create([
        'name' => 'Email Donor',
        'email' => 'email.donor@example.com',
        'phone' => '9888777666',
        'city' => 'Ahmedabad',
    ]);

    $this->postJson(route('donate.otp.send'), [
        'login_method' => 'email',
        'donor_email' => 'email.donor@example.com',
    ])->assertOk()
        ->assertJson([
            'found' => true,
            'sent' => true,
            'channel' => 'email',
        ]);

    $mail = null;
    Mail::assertSent(DonorOtpMail::class, function (DonorOtpMail $sent) use (&$mail, $donor) {
        $mail = $sent;

        return $sent->hasTo($donor->email);
    });

    $this->postJson(route('donate.otp.verify'), [
        'login_method' => 'email',
        'donor_email' => 'email.donor@example.com',
        'otp' => $mail->otp,
    ])->assertOk()
        ->assertJson([
            'verified' => true,
            'signed_in' => true,
            'profile' => [
                'donor_email' => 'email.donor@example.com',
                'donor_name' => 'Email Donor',
                'city' => 'Ahmedabad',
            ],
        ]);
});

it('sends whatsapp otp with body and copy-code button parameters', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    AisensyAccount::create([
        'name' => 'OTP Account',
        'api_key' => 'otp-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    Setting::query()->updateOrInsert(
        ['key' => Setting::AISENSY_OTP_CAMPAIGN],
        [
            'value' => 'login_otp',
            'label' => 'AiSensy OTP Campaign',
            'description' => 'OTP campaign',
            'group' => 'notifications',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    Donor::factory()->create([
        'name' => 'OTP Donor',
        'email' => 'otp.donor@example.com',
        'phone' => '9876501234',
    ]);

    $this->postJson(route('donate.otp.send'), [
        'login_method' => 'phone',
        'donor_phone' => '9876501234',
        'phone_dial_code' => '91',
        'donor_country_code' => 'IN',
    ])->assertOk()
        ->assertJson([
            'found' => true,
            'sent' => true,
            'channel' => 'whatsapp',
        ]);

    Http::assertSent(function (Request $request) {
        $data = $request->data();

        if (($data['campaignName'] ?? null) !== 'login_otp') {
            return false;
        }

        $otp = (string) ($data['templateParams'][0] ?? '');

        return $otp !== ''
            && ($data['buttons'][0]['sub_type'] ?? null) === 'url'
            && ($data['buttons'][0]['parameters'][0]['type'] ?? null) === 'text'
            && ($data['buttons'][0]['parameters'][0]['text'] ?? null) === $otp
            && ($data['destination'] ?? null) === '919876501234';
    });
});
