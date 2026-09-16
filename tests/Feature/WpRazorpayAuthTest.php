<?php

use App\Models\Cause;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function wpRazorpayDonationPayload(Cause $cause): array
{
    return [
        'cause' => $cause->slug,
        'amount' => 1000,
        'quantity' => 1,
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9999999999',
        'address' => '123 Test Street',
        'pincode' => '400001',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'country' => 'India',
        'consent_indian_citizen' => true,
    ];
}

it('rejects wordpress razorpay requests when the api token is missing', function () {
    $cause = Cause::factory()->create([
        'allow_custom_amount' => true,
        'is_active' => true,
        'pan_required' => false,
    ]);

    config()->set('app.wp_api_token', null);

    $response = $this->postJson('/api/wp-razorpay', wpRazorpayDonationPayload($cause));

    $response->assertForbidden();
    $response->assertJson(['error' => 'Unauthorized']);
});

it('rejects wordpress razorpay requests when the configured api token is blank', function () {
    $cause = Cause::factory()->create([
        'allow_custom_amount' => true,
        'is_active' => true,
        'pan_required' => false,
    ]);

    config()->set('app.wp_api_token', '');

    $response = $this->postJson('/api/wp-razorpay', wpRazorpayDonationPayload($cause));

    $response->assertForbidden();
    $response->assertJson(['error' => 'Unauthorized']);
});

it('rejects wordpress razorpay requests with a mismatched api token', function () {
    $cause = Cause::factory()->create([
        'allow_custom_amount' => true,
        'is_active' => true,
        'pan_required' => false,
    ]);

    config()->set('app.wp_api_token', 'expected-token');

    $response = $this
        ->withHeader('X-WP-TOKEN', 'wrong-token')
        ->postJson('/api/wp-razorpay', wpRazorpayDonationPayload($cause));

    $response->assertForbidden();
    $response->assertJson(['error' => 'Unauthorized']);
});
