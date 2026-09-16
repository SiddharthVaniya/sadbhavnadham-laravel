<?php

use App\Models\Donor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Mail::fake();
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
    ]);
});

function sendOtpAttempt(string $phone): TestResponse
{
    return test()->postJson('/api/donate/otp/send', [
        'login_method' => 'phone',
        'donor_phone' => $phone,
        'phone_dial_code' => '91',
        'donor_country_code' => 'IN',
    ]);
}

it('does not block other donors when many otp sends share the nextjs proxy ip', function () {
    for ($i = 1; $i <= 11; $i++) {
        $phone = str_pad((string) (9000000000 + $i), 10, '0', STR_PAD_LEFT);

        Donor::factory()->create([
            'phone' => $phone,
            'email' => "otp-donor{$i}@example.com",
        ]);

        sendOtpAttempt($phone)
            ->assertOk()
            ->assertJsonPath('sent', true)
            ->assertJsonMissing(['message' => 'Too Many Attempts.']);
    }
});

it('still throttles repeated otp sends for the same donor identity', function () {
    Donor::factory()->create([
        'phone' => '9876543210',
        'email' => 'same-otp@example.com',
    ]);

    for ($i = 1; $i <= 8; $i++) {
        $response = sendOtpAttempt('9876543210');

        expect($response->status())->toBeIn([200, 422]);
    }

    sendOtpAttempt('9876543210')->assertTooManyRequests();
});
