<?php

use App\Models\AnalyticsEvent;
use App\Models\Cause;
use App\Models\DonationCampaign;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use App\Services\DonationAttributionService;
use App\Services\DonationPaymentService;
use App\Support\AdminAnalyticsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('stamps facebook attribution from fbclid when utm params are missing', function () {
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-fbclid-1',
        'donor_name' => 'Meta Click Donor',
        'donor_email' => 'meta-click@example.com',
        'donor_phone' => '9876543299',
        'currency' => 'INR',
        'total_amount' => 501,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $request = Request::create('/donate/checkout?fbclid=IwAR0metaClickTest', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
    ]);

    app(\App\Services\AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->utm_source)->toBe('meta')
        ->and($order->utm_medium)->toBe('paid_social')
        ->and($order->attr_source)->toBe('meta')
        ->and($order->attr_platform)->toBe('facebook')
        ->and(DonationAttributionService::trafficSourceLabel($order))->toBe('Meta · Facebook · Paid social')
        ->and($order->landing_path)->toContain('fbclid=1');
});

it('stamps google cpc attribution from gclid when utm params are missing', function () {
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-gclid-1',
        'donor_name' => 'Google Click Donor',
        'donor_email' => 'google-click@example.com',
        'donor_phone' => '9876543298',
        'currency' => 'INR',
        'total_amount' => 701,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $request = Request::create('/donate/checkout?gclid=Cj0KCQgoogleTest', 'POST');

    app(\App\Services\AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->utm_source)->toBe('google')
        ->and($order->utm_medium)->toBe('cpc')
        ->and(DonationAttributionService::trafficSourceLabel($order))->toBe('Google · Paid search');
});

it('labels blank utm traffic as Direct and google referrer as Google', function () {
    expect(DonationAttributionService::resolveTrafficSourceBucket(null, null, null))->toBe('organic')
        ->and(DonationAttributionService::resolveTrafficSourceBucket(null, null, 'https://www.google.com/search?q=ngo'))
        ->toBe('google')
        ->and(DonationAttributionService::resolveTrafficSourceBucket(null, null, 'https://l.facebook.com/l.php?u=x', '/donate/tree?fbclid=1'))
        ->toBe('facebook');
});

it('stamps utm referrer and web channel on checkout started', function () {
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-checkout-1',
        'donor_name' => 'Source Donor',
        'donor_email' => 'source@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $request = Request::create('/donate/checkout', 'POST', [
        'utm_source' => 'facebook',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'spring',
        'utm_content' => 'pritesh',
    ], [], [], [
        'HTTP_REFERER' => 'https://www.facebook.com/ads',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
    ]);

    app(\App\Services\AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->source_channel)->toBe(DonationAttributionService::CHANNEL_WEB)
        ->and($order->utm_source)->toBe('facebook')
        ->and($order->utm_medium)->toBe('cpc')
        ->and($order->attr_source)->toBe('meta')
        ->and($order->attr_platform)->toBe('facebook')
        ->and($order->attr_medium)->toBe('paid_social')
        ->and($order->utm_campaign)->toBe('spring')
        ->and($order->utm_content)->toBe('pritesh')
        ->and($order->referrer)->toContain('facebook.com')
        ->and($order->landing_path)->toBe('donate/checkout')
        ->and($order->device_type)->toBe('mobile');
});

it('stamps nextjs checkout cookies including a staff sid onto the donation', function () {
    $staff = User::factory()->withReferralCode('xvjsrg')->create();

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-next-sid-1',
        'donor_name' => 'Cookie Donor',
        'donor_email' => 'cookie@example.com',
        'donor_phone' => '9876543201',
        'currency' => 'INR',
        'total_amount' => 3000,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $request = Request::create('/api/donate/checkout', 'POST', [
        'utm_source' => 'meta',
        'utm_medium' => 'Facebook_Desktop_Feed',
        'utm_campaign' => '22/08 | Sadbhavna | Tree Plantation',
        'utm_content' => '22/08 | Sadbhavna | Tree Plantation | Guj | Vid',
        'utm_id' => '120257765605850197',
        'utm_term' => '120257765605830197',
        'sid' => 'xvjsrg',
        'amt' => '3000',
        'ptype' => 'ot',
        'landing_url' => 'https://sadbhavnadham.org/donate/tree-plantation?sid=xvjsrg&utm_source=meta',
        'landing_path' => '/donate/tree-plantation',
        'source_channel' => 'web',
    ]);

    app(\App\Services\AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->utm_source)->toBe('meta')
        ->and($order->utm_medium)->toBe('Facebook_Desktop_Feed')
        ->and($order->utm_campaign)->toBe('22/08 | Sadbhavna | Tree Plantation')
        ->and($order->partner_code)->toBe('xvjsrg')
        ->and($order->partner_user_id)->toBe($staff->id)
        ->and($order->meta_campaign_id)->toBe('120257765605850197')
        ->and($order->landing_path)->toBe('/donate/tree-plantation');
});

it('stamps campaign channel when the order item has a campaign', function () {
    $cause = Cause::factory()->create();
    $campaign = DonationCampaign::factory()->create([
        'cause_id' => $cause->id,
        'name' => 'Peepal Drive',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-campaign-1',
        'donor_name' => 'Campaign Donor',
        'donor_email' => 'campaign@example.com',
        'donor_phone' => '9876543211',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'donation_campaign_id' => $campaign->id,
        'cause' => $cause->slug,
        'title' => 'Tree',
        'quantity' => 1,
        'unit_amount' => 1000,
        'amount' => 1000,
    ]);

    $request = Request::create('/give/'.$campaign->slug, 'POST', [
        'utm_source' => 'instagram',
    ]);

    app(\App\Services\AnalyticsService::class)->trackCheckoutStarted($request, $order->fresh());

    $order->refresh();

    expect($order->source_channel)->toBe(DonationAttributionService::CHANNEL_CAMPAIGN)
        ->and($order->source_campaign_id)->toBe($campaign->id)
        ->and($order->utm_source)->toBe('instagram');
});

it('backfills attribution from checkout_started when paid without stamped fields', function () {
    Bus::fake();

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-backfill-1',
        'donor_name' => 'Backfill Donor',
        'donor_email' => 'backfill@example.com',
        'donor_phone' => '9876543212',
        'currency' => 'INR',
        'total_amount' => 750,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_CHECKOUT_STARTED,
        'donation_order_id' => $order->id,
        'utm_source' => 'google',
        'utm_medium' => 'organic',
        'utm_campaign' => 'brand',
        'referrer' => 'https://www.google.com/',
        'path' => '/donate/tree',
        'device_type' => 'desktop',
        'amount' => 750,
        'created_at' => now(),
    ]);

    $order->markAsPaid('pay_backfill_1');
    app(DonationPaymentService::class)->completePaidOrder($order->fresh());

    $order->refresh();

    expect($order->utm_source)->toBe('google')
        ->and($order->utm_medium)->toBe('organic')
        ->and($order->utm_campaign)->toBe('brand')
        ->and($order->referrer)->toContain('google.com')
        ->and($order->landing_path)->toBe('/donate/tree')
        ->and($order->device_type)->toBe('desktop')
        ->and($order->source_channel)->toBe(DonationAttributionService::CHANNEL_WEB);

    $paidEvent = AnalyticsEvent::query()
        ->where('donation_order_id', $order->id)
        ->where('event_type', AnalyticsEvent::TYPE_DONATION_PAID)
        ->first();

    expect($paidEvent)->not->toBeNull()
        ->and($paidEvent->utm_source)->toBe('google')
        ->and($paidEvent->referrer)->toContain('google.com');
});

it('sets offline source channel when creating an offline donation', function () {
    Bus::fake();

    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'manage donations']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['view donations', 'manage donations']);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    actingAs($user)
        ->post(route('admin.donations.store'), [
            'donor_name' => 'Offline Donor',
            'donor_email' => '',
            'donor_phone' => '9898989898',
            'address' => '123 Test Street',
            'pincode' => '360001',
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'country' => 'INDIA',
            'payment_provider' => DonationOrder::PROVIDER_OFFLINE,
            'total_amount' => 2000,
            'send_receipt_email' => false,
        ])
        ->assertRedirect(route('admin.donations.offline'));

    $order = DonationOrder::query()->where('donor_phone', '9898989898')->first();

    expect($order)->not->toBeNull()
        ->and($order->source_channel)->toBe(DonationAttributionService::CHANNEL_OFFLINE);
});

it('exposes source on the donation detail page', function () {
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    Permission::firstOrCreate(['name' => 'manage receipts']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['view donations', 'view all donations', 'manage receipts']);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    $partner = User::factory()->create([
        'name' => 'Ashvini Partner',
        'referral_code' => 'ashvini',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-show-1',
        'donor_name' => 'Detail Donor',
        'donor_email' => 'detail@example.com',
        'donor_phone' => '9876543213',
        'currency' => 'INR',
        'total_amount' => 1200,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'source_channel' => DonationAttributionService::CHANNEL_WEB,
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_content' => 'pritesh',
        'referrer' => 'https://mail.google.com/',
        'landing_path' => '/donate/tree',
        'device_type' => 'desktop',
    ]);

    $order->forceFill([
        'partner_user_id' => $partner->id,
        'partner_code' => 'ashvini',
    ])->save();

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.source.channel', DonationAttributionService::CHANNEL_WEB)
            ->where('donation.source.channel_label', 'Web checkout')
            ->where('donation.source.utm_source', 'newsletter')
            ->where('donation.source.utm_medium', 'email')
            ->where('donation.source.utm_content', 'pritesh')
            ->where('donation.source.landing_path', '/donate/tree')
            ->where('donation.source.partner_code', 'ashvini')
            ->where('donation.source.partner_name', 'Ashvini Partner'));
});

it('includes paid source breakdowns in analytics report', function () {
    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-analytics-1',
        'donor_name' => 'Analytics Donor',
        'donor_email' => 'analytics@example.com',
        'donor_phone' => '9876543214',
        'currency' => 'INR',
        'total_amount' => 3000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'source_channel' => DonationAttributionService::CHANNEL_WEB,
        'utm_source' => 'facebook',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'spring',
        'utm_content' => 'pritesh',
        'referrer' => 'https://www.facebook.com/page',
        'attr_source' => 'meta',
        'attr_medium' => 'paid_social',
        'attr_platform' => 'facebook',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_OFFLINE,
        'provider_order_id' => 'attr-analytics-2',
        'donor_name' => 'Offline Analytics',
        'donor_email' => 'offline-a@example.com',
        'donor_phone' => '9876543215',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'source_channel' => DonationAttributionService::CHANNEL_OFFLINE,
    ]);

    $report = AdminAnalyticsData::report('all');

    expect($report['paidByChannel'])->toBeArray()
        ->and(collect($report['paidByChannel'])->pluck('channel')->all())
        ->toContain(DonationAttributionService::CHANNEL_WEB)
        ->toContain(DonationAttributionService::CHANNEL_OFFLINE)
        ->and(collect($report['paidByUtm'])->first())
        ->toMatchArray([
            'source' => 'Meta · Facebook',
            'medium' => 'paid_social',
            'campaign' => 'spring',
            'content' => 'pritesh',
            'paid_orders' => 1,
            'revenue' => 3000.0,
        ])
        ->and(collect($report['paidByEmployee'])->first())
        ->toMatchArray([
            'content' => 'pritesh',
            'paid_orders' => 1,
            'revenue' => 3000.0,
        ])
        ->and(collect($report['paidByReferrer'])->pluck('referrer')->all())
        ->toContain('facebook.com');
});

it('saves ip and location on donate checkout but not on visit clicks', function () {
    Illuminate\Support\Facades\Http::fake([
        'http://ip-api.com/*' => Illuminate\Support\Facades\Http::response([
            'status' => 'success',
            'country' => 'India',
            'countryCode' => 'IN',
            'regionName' => 'Gujarat',
            'city' => 'Ahmedabad',
            'lat' => 23.0225,
            'lon' => 72.5714,
            'query' => '203.0.113.10',
        ]),
    ]);

    $cause = Cause::factory()->create(['title' => 'Old Age Home']);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-ip-donate-1',
        'donor_name' => 'IP Donor',
        'donor_email' => 'ip-donor@example.com',
        'donor_phone' => '9876543211',
        'currency' => 'INR',
        'total_amount' => 501,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->title,
        'title' => $cause->title,
        'quantity' => 1,
        'unit_amount' => 501,
        'amount' => 501,
    ]);

    $checkoutRequest = Request::create('/donate/checkout', 'POST', [], [], [], [
        'REMOTE_ADDR' => '203.0.113.10',
    ]);

    app(\App\Services\AnalyticsService::class)->trackCheckoutStarted($checkoutRequest, $order);
    $order->refresh();

    expect($order->ip_address)->toBe('203.0.113.10')
        ->and($order->ip_country_code)->toBe('IN')
        ->and($order->ip_country_name)->toBe('India')
        ->and($order->ip_region_name)->toBe('Gujarat')
        ->and($order->ip_city)->toBe('Ahmedabad')
        ->and($order->ip_lat)->toBe(23.0225)
        ->and($order->ip_lng)->toBe(72.5714);

    $visitRequest = Request::create('/donate/old-age-home', 'GET', [], [], [], [
        'REMOTE_ADDR' => '203.0.113.20',
    ]);

    app(\App\Services\AnalyticsService::class)->trackVisitCause($visitRequest, $cause);

    $visitEvent = AnalyticsEvent::query()
        ->where('event_type', AnalyticsEvent::TYPE_VISIT_CAUSE)
        ->latest('id')
        ->first();

    expect($visitEvent)->not->toBeNull()
        ->and($visitEvent->ip_address)->toBeNull()
        ->and($visitEvent->country_code)->toBeNull()
        ->and($visitEvent->city)->toBeNull();
});
