<?php

use App\Models\Cause;
use App\Models\DonationOrder;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('accepts attribution fields from the nextjs checkout endpoint', function () {
    config(['app.wp_api_token' => 'configured-secret-token']);

    Cause::factory()->create([
        'slug' => 'old-age-home',
        'allow_custom_amount' => true,
        'is_active' => true,
        'pan_required' => false,
    ]);

    $response = $this
        ->withHeader('X-WP-TOKEN', 'configured-secret-token')
        ->postJson('/api/donate/checkout', [
            'cause' => 'old-age-home',
            'amount' => 500,
            'utm_source' => 'staff',
            'utm_medium' => 'referral',
            'sid' => 'ac',
            'utm_campaign' => 'Shravan',
            'landing_path' => '/donate/old-age-home',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['donor_name', 'donor_email', 'donor_phone']);
});

it('defaults nextjs checkout to web channel when source channel is omitted', function () {
    config(['app.wp_api_token' => 'configured-secret-token']);

    $response = $this
        ->withHeader('X-WP-TOKEN', 'configured-secret-token')
        ->postJson('/api/donate/checkout', [
            'cause' => 'missing-cause',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['cause']);
});

it('protects the nextjs checkout endpoint with the frontend api token', function () {
    Cause::factory()->create([
        'slug' => 'tree-plantation',
        'allow_custom_amount' => true,
        'is_active' => true,
        'pan_required' => false,
    ]);

    $this->postJson('/api/donate/checkout', [
        'cause' => 'tree-plantation',
        'amount' => 500,
    ])->assertForbidden();
});

it('allows the nextjs origin to call the checkout api', function () {
    $response = $this
        ->withHeaders([
            'Origin' => 'https://sadbhavnadham.org',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-wp-token',
        ])
        ->options('/api/donate/checkout');

    $response->assertSuccessful();
    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->toBe('https://sadbhavnadham.org');
});

it('stamps nextjs json attribution onto the order without a donate-subdomain cookie', function () {
    $partner = User::factory()->create(['referral_code' => 'ashvini']);
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'next-json-1',
        'donor_name' => 'Next Donor',
        'donor_email' => 'next-json@example.com',
        'donor_phone' => '9876500111',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $request = Request::create('/api/donate/checkout', 'POST', [
        'cause' => 'tree-plantation',
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'tree_plantation_august',
        'utm_content' => 'Feed ad',
        'utm_term' => 'Lookalike 25-45',
        'utm_id' => '120211',
        'sid' => 'ashvini',
        'aid' => '120213',
        'utm_term' => '120212',
        'platform' => 'fb',
        'placement' => 'feed',
        'landing_path' => '/causes/old-age-home?utm_source=meta&sid=ashvini',
        'source_channel' => 'web',
    ]);

    app(AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->utm_source)->toBe('meta')
        ->and($order->utm_campaign)->toBe('tree_plantation_august')
        ->and($order->partner_user_id)->toBe($partner->id)
        ->and($order->partner_code)->toBe('ashvini')
        ->and($order->meta_campaign_id)->toBe('120211')
        ->and($order->meta_adset_id)->toBe('120212')
        ->and($order->meta_ad_id)->toBe('120213')
        ->and($order->landing_path)->toStartWith('/causes/old-age-home')
        ->and($order->attr_source)->toBe('meta');
});

it('ignores a leftover donate-subdomain cookie when nextjs sends first-touch json', function () {
    $partner = User::factory()->create(['referral_code' => 'ashvini']);
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'next-json-cookie-1',
        'donor_name' => 'Next Donor',
        'donor_email' => 'next-cookie@example.com',
        'donor_phone' => '9876500112',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $request = Request::create('/api/donate/checkout', 'POST', [
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'tree_plantation_august',
        'utm_id' => '120211',
        'sid' => 'ashvini',
        'landing_path' => '/page?utm_source=meta&sid=ashvini',
        'source_channel' => 'web',
    ]);

    $request->cookies->set(AnalyticsService::UTM_COOKIE, json_encode([
        'utm_source' => 'direct',
        'utm_medium' => 'none',
        'landing_path' => '/donate/old-age-home',
    ], JSON_UNESCAPED_UNICODE));

    $analytics = app(AnalyticsService::class);
    $analytics->discardHostUtmCookie($request);
    $analytics->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->utm_source)->toBe('meta')
        ->and($order->utm_campaign)->toBe('tree_plantation_august')
        ->and($order->partner_user_id)->toBe($partner->id)
        ->and($order->landing_path)->toStartWith('/page');
});
