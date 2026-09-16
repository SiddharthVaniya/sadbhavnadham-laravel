<?php

use App\Jobs\SendBirthdayWhatsAppJob;
use App\Models\AisensyAccount;
use App\Models\Donor;
use App\Models\Setting;
use App\Services\AiSensyService;
use App\Services\BirthdayImageService;
use App\Services\DonationWhatsAppPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_BIRTHDAY_WHATSAPP],
        [
            'value' => '1',
            'label' => 'Send Birthday WhatsApp',
            'description' => 'Birthday WhatsApp',
            'group' => 'notifications',
        ],
    );

    Setting::query()->updateOrCreate(
        ['key' => Setting::AISENSY_BIRTHDAY_CAMPAIGN],
        [
            'value' => 'birthday-campaign',
            'label' => 'AiSensy Birthday Campaign',
            'description' => 'Campaign',
            'group' => 'notifications',
        ],
    );
});

it('allows birthday send when dob is today and phone is valid', function () {
    $donor = Donor::factory()->create([
        'date_of_birth' => now()->subYears(30)->toDateString(),
        'phone' => '9876543210',
        'birthday_whatsapp_sent_on' => null,
    ]);

    expect(app(DonationWhatsAppPolicy::class)->shouldSendBirthday($donor))->toBeTrue();
});

it('skips birthday send when phone is missing or placeholder', function (string $phone) {
    $donor = Donor::factory()->create([
        'date_of_birth' => now()->subYears(30)->toDateString(),
        'phone' => $phone,
    ]);

    expect(app(DonationWhatsAppPolicy::class)->shouldSendBirthday($donor))->toBeFalse();
})->with([
    'empty' => [''],
    'placeholder' => ['u-d4e5f6'],
    'letters' => ['upi-a1b2c3'],
    'short' => ['98765'],
]);

it('skips birthday send when already sent today', function () {
    $donor = Donor::factory()->create([
        'date_of_birth' => now()->subYears(30)->toDateString(),
        'phone' => '9876543210',
        'birthday_whatsapp_sent_on' => now()->toDateString(),
    ]);

    expect(app(DonationWhatsAppPolicy::class)->shouldSendBirthday($donor))->toBeFalse();
});

it('force-allows birthday send even when dob is not today', function () {
    $donor = Donor::factory()->create([
        'date_of_birth' => now()->subYears(30)->subMonths(2)->toDateString(),
        'phone' => '9876543210',
        'birthday_whatsapp_sent_on' => now()->toDateString(),
    ]);

    expect(app(DonationWhatsAppPolicy::class)->shouldSendBirthday($donor, now(), true))->toBeTrue();
});

it('targets a specific phone with --phone and --force even when dob is not today', function () {
    Bus::fake();

    Donor::factory()->create([
        'name' => 'Target Donor',
        'phone' => '7211182822',
        'date_of_birth' => now()->subYears(25)->subMonths(3)->toDateString(),
        'birthday_whatsapp_sent_on' => null,
    ]);

    Donor::factory()->create([
        'name' => 'Other Birthday',
        'phone' => '9876543210',
        'date_of_birth' => now()->subYears(40)->toDateString(),
        'birthday_whatsapp_sent_on' => null,
    ]);

    $this->artisan('donors:send-birthday-whatsapp', [
        '--phone' => '7211182822',
        '--force' => true,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('7211182822')
        ->expectsOutputToContain('Would dispatch 1 birthday WhatsApp job(s)')
        ->assertSuccessful();

    Bus::assertNothingDispatched();
});

it('dry-run lists eligible donors without dispatching jobs', function () {
    Bus::fake();

    Donor::factory()->create([
        'name' => 'Birthday Donor',
        'date_of_birth' => now()->subYears(25)->toDateString(),
        'phone' => '9876543210',
        'birthday_whatsapp_sent_on' => null,
    ]);

    Donor::factory()->create([
        'name' => 'No Phone Donor',
        'date_of_birth' => now()->subYears(25)->toDateString(),
        'phone' => 'u-placeholder',
    ]);

    $this->artisan('donors:send-birthday-whatsapp', ['--dry-run' => true])
        ->expectsOutputToContain('[dry-run] donor #')
        ->expectsOutputToContain('Would dispatch 1 birthday WhatsApp job(s)')
        ->assertSuccessful();

    Bus::assertNothingDispatched();
});

it('dispatches birthday jobs for eligible donors', function () {
    Bus::fake();

    $donor = Donor::factory()->create([
        'date_of_birth' => now()->subYears(40)->toDateString(),
        'phone' => '9876543210',
        'birthday_whatsapp_sent_on' => null,
    ]);

    $this->artisan('donors:send-birthday-whatsapp')
        ->assertSuccessful();

    Bus::assertDispatched(SendBirthdayWhatsAppJob::class, function (SendBirthdayWhatsAppJob $job) use ($donor): bool {
        $reflection = new ReflectionProperty($job, 'donor');

        return $reflection->getValue($job)->is($donor);
    });
});

it('does not dispatch when birthday was already sent today', function () {
    Bus::fake();

    Donor::factory()->create([
        'date_of_birth' => now()->subYears(40)->toDateString(),
        'phone' => '9876543210',
        'birthday_whatsapp_sent_on' => now()->toDateString(),
    ]);

    $this->artisan('donors:send-birthday-whatsapp')
        ->expectsOutputToContain('Dispatched 0 birthday WhatsApp job(s)')
        ->assertSuccessful();

    Bus::assertNothingDispatched();
});

it('marks donor after successful birthday whatsapp job', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
        '*' => Http::response('birthday-image', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $this->mock(BirthdayImageService::class, function ($mock): void {
        $mock->shouldReceive('whatsappMediaUrl')
            ->once()
            ->andReturn('https://donate.example.test/storage/birthdays/birthday-1.png');
        $mock->shouldReceive('donorDisplayName')
            ->andReturn('Ramesh Patel');
    });

    $donor = Donor::factory()->create([
        'name' => 'Ramesh Patel',
        'date_of_birth' => now()->subYears(32)->toDateString(),
        'phone' => '9876543210',
        'birthday_whatsapp_sent_on' => null,
    ]);

    (new SendBirthdayWhatsAppJob($donor, now()->toDateString()))->handle(
        app(AiSensyService::class),
        app(DonationWhatsAppPolicy::class),
    );

    expect($donor->fresh()->birthday_whatsapp_sent_on?->toDateString())->toBe(now()->toDateString());
});

it('sends birthday aisensy payload with media url and donor name', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
        '*' => Http::response('birthday-image', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $mediaUrl = 'https://donate.example.test/storage/birthdays/birthday-99.png';

    $this->mock(BirthdayImageService::class, function ($mock) use ($mediaUrl): void {
        $mock->shouldReceive('whatsappMediaUrl')->once()->andReturn($mediaUrl);
        $mock->shouldReceive('donorDisplayName')->andReturn('Vijay Dobariya');
    });

    $donor = Donor::factory()->create([
        'name' => 'Vijay Dobariya',
        'date_of_birth' => now()->subYears(45)->toDateString(),
        'phone' => '9876543210',
    ]);

    expect(app(AiSensyService::class)->sendBirthdayWhatsApp($donor))->toBeTrue();

    Http::assertSent(function (Request $request) use ($mediaUrl): bool {
        $data = $request->data();

        return ($data['campaignName'] ?? null) === 'birthday-campaign'
            && ($data['destination'] ?? null) === '919876543210'
            && ($data['userName'] ?? null) === 'Vijay Dobariya'
            && ($data['templateParams'][0] ?? null) === 'Vijay Dobariya'
            && ($data['media']['url'] ?? null) === $mediaUrl
            && ($data['media']['filename'] ?? null) === 'birthday-99.png';
    });
});

it('returns a public birthday image url when a png already exists', function () {
    Storage::fake('public');
    config()->set('donation.birthday.enabled', true);
    config()->set('donation.birthday.public_base_url', 'https://donate.example.test');
    config()->set('donation.birthday.template', storage_path('framework/testing/missing-birthday-template.jpg'));
    config()->set('donation.birthday.font', storage_path('framework/testing/missing-birthday-font.ttf'));

    $donor = Donor::factory()->create([
        'name' => 'Existing Image Donor',
    ]);

    $relative = 'birthdays/birthday-'.$donor->id.'-'.now()->year.'.png';
    Storage::disk('public')->put($relative, 'fake-png-bytes');

    $url = app(BirthdayImageService::class)->whatsappMediaUrl($donor);

    expect($url)->toBe('https://donate.example.test/storage/'.$relative);
});

it('keeps gujarati and hindi names unchanged and picks matching fonts', function (string $name, string $family) {
    $donor = Donor::factory()->make(['name' => $name]);
    $service = app(BirthdayImageService::class);

    expect($service->donorDisplayName($donor))->toBe($name)
        ->and($service->resolveNameTypography($name)['family'])->toBe($family);
})->with([
    'gujarati' => ['મોનિલ વેકરીયા', 'noto sans gujarati'],
    'hindi' => ['राहुल शर्मा', 'noto sans devanagari'],
]);

it('title-cases latin names and uses poppins', function () {
    $donor = Donor::factory()->make(['name' => 'monil vekariya']);
    $service = app(BirthdayImageService::class);

    expect($service->donorDisplayName($donor))->toBe('Monil Vekariya')
        ->and($service->resolveNameTypography('Monil Vekariya')['family'])->toBe('poppins');
});
