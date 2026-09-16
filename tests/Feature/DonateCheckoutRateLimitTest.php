<?php

use App\Models\Cause;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.wp_api_token' => 'configured-secret-token']);
    Cache::flush();

    Cause::factory()->create([
        'slug' => 'old-age-home',
        'allow_custom_amount' => true,
        'is_active' => true,
        'pan_required' => false,
    ]);
});

function checkoutAttempt(array $overrides = [])
{
    return test()
        ->withHeader('X-WP-TOKEN', 'configured-secret-token')
        ->postJson('/api/donate/checkout', array_merge([
            'cause' => 'old-age-home',
            'amount' => 500,
        ], $overrides));
}

it('does not block other donors when many checkouts share the nextjs proxy ip', function () {
    for ($i = 1; $i <= 11; $i++) {
        checkoutAttempt([
            'donor_email' => "donor{$i}@example.com",
            'donor_phone' => str_pad((string) (9000000000 + $i), 10, '0', STR_PAD_LEFT),
        ])->assertUnprocessable()
            ->assertJsonMissing(['message' => 'Too Many Attempts.']);
    }
});

it('still throttles repeated checkout attempts from the same donor', function () {
    for ($i = 1; $i <= 30; $i++) {
        checkoutAttempt([
            'donor_email' => 'same-donor@example.com',
            'donor_phone' => '9876543210',
        ])->assertUnprocessable();
    }

    checkoutAttempt([
        'donor_email' => 'same-donor@example.com',
        'donor_phone' => '9876543210',
    ])->assertTooManyRequests();
});
