<?php

use App\Models\Cause;
use App\Services\DonationAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults wordpress attribution fields on the wp razorpay endpoint before validation', function () {
    config(['app.wp_api_token' => 'configured-secret-token']);

    $response = $this
        ->withHeader('X-WP-TOKEN', 'configured-secret-token')
        ->postJson('/api/wp-razorpay', [
            'cause' => 'missing-cause',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['cause']);
});

it('exposes the wordpress channel label for admin reporting', function () {
    expect(DonationAttributionService::channelLabel(DonationAttributionService::CHANNEL_WORDPRESS))
        ->toBe('WordPress site');
});

it('accepts a cause slug that exists when other required fields are missing', function () {
    config(['app.wp_api_token' => 'configured-secret-token']);

    Cause::factory()->create([
        'slug' => 'old-age-home',
        'allow_custom_amount' => true,
        'is_active' => true,
        'pan_required' => false,
    ]);

    $response = $this
        ->withHeader('X-WP-TOKEN', 'configured-secret-token')
        ->postJson('/api/wp-razorpay', [
            'cause' => 'old-age-home',
            'amount' => 500,
            'source_channel' => 'wordpress',
            'utm_source' => 'wordpress',
            'landing_path' => '/donate-old-age-home/',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['donor_name', 'donor_email', 'donor_phone']);
});

it('evaluates pan requirement for wordpress using the same threshold rules', function () {
    config(['app.wp_api_token' => 'configured-secret-token']);

    Cause::factory()->create([
        'slug' => 'old-age-home',
        'allow_custom_amount' => true,
        'is_active' => true,
        'pan_required' => true,
    ]);

    $below = $this
        ->withHeader('X-WP-TOKEN', 'configured-secret-token')
        ->postJson('/api/wp-pan-requirement', [
            'cause' => 'old-age-home',
            'amount' => 5000,
            'donor_phone' => '9876543210',
            'donor_email' => 'donor@example.com',
        ]);

    $below->assertSuccessful();
    $below->assertJsonPath('required', false);
    $below->assertJsonPath('threshold', 100000);

    $above = $this
        ->withHeader('X-WP-TOKEN', 'configured-secret-token')
        ->postJson('/api/wp-pan-requirement', [
            'cause' => 'old-age-home',
            'amount' => 100000,
            'donor_phone' => '9876543210',
            'donor_email' => 'donor@example.com',
        ]);

    $above->assertSuccessful();
    $above->assertJsonPath('required', true);
});
