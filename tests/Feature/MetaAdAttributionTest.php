<?php

use App\Models\DonationOrder;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\AdminStaffReferralsData;
use App\Support\StaffReferral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function metaAdOrder(string $providerOrderId): DonationOrder
{
    return DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => $providerOrderId,
        'donor_name' => 'Meta Ad Donor',
        'donor_email' => $providerOrderId.'@example.com',
        'donor_phone' => '9876500001',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PENDING,
    ]);
}

it('stamps the partner and meta ad ids from url parameters', function () {
    $partner = User::factory()->create(['referral_code' => 'ashvini']);
    $order = metaAdOrder('meta-exact-1');

    $request = Request::create(
        '/donate/tree-plantation?utm_source=meta&utm_medium=paid_social&sid=ashvini'
        .'&utm_campaign=Monsoon+Trees&utm_id=120211&utm_term=120212&aid=120213&placement=feed&platform=fb',
        'POST'
    );

    app(AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->partner_user_id)->toBe($partner->id)
        ->and($order->partner_code)->toBe('ashvini')
        ->and($order->meta_campaign_id)->toBe('120211')
        ->and($order->meta_adset_id)->toBe('120212')
        ->and($order->meta_ad_id)->toBe('120213')
        ->and($order->attr_source)->toBe('meta');
});

it('ignores unexpanded meta tokens and unknown partner codes', function () {
    $order = metaAdOrder('meta-exact-2');

    $request = Request::create(
        '/donate/tree-plantation?utm_source=meta&sid=nobody-here&utm_id={{campaign.id}}&aid={{ad.id}}',
        'POST'
    );

    app(AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->partner_user_id)->toBeNull()
        ->and($order->partner_code)->toBe('nobody-here')
        ->and($order->meta_campaign_id)->toBeNull()
        ->and($order->meta_ad_id)->toBeNull();
});

it('resolves the partner from a staff referral link without sid', function () {
    $partner = User::factory()->create(['referral_code' => 'kiran']);
    $order = metaAdOrder('meta-exact-3');

    $request = Request::create('/donate/tree-plantation?utm_source=staff&utm_medium=referral&utm_content=kiran', 'POST');

    app(AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->partner_user_id)->toBe($partner->id)
        ->and($order->partner_code)->toBe('kiran');
});

it('counts a partner exactly once when sid is present', function () {
    $ashvini = User::factory()->create(['name' => 'Ashvini', 'referral_code' => 'ashvini']);
    $other = User::factory()->create(['name' => 'Kiran', 'referral_code' => 'kiran']);

    // Campaign name mentions Kiran, but sid says the donation belongs to Ashvini.
    metaAdOrder('meta-exact-4')->forceFill([
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'ashvini',
        'utm_campaign' => 'Kiran Monsoon Push',
        'status' => DonationOrder::STATUS_PAID,
    ])->save();

    $ashviniCount = DonationOrder::query()
        ->tap(fn ($query) => AdminStaffReferralsData::applyPartnerAttributionFilter($query, $ashvini))
        ->count();

    $kiranCount = DonationOrder::query()
        ->tap(fn ($query) => AdminStaffReferralsData::applyPartnerAttributionFilter($query, $other))
        ->count();

    expect($ashviniCount)->toBe(1)
        ->and($kiranCount)->toBe(0);
});

it('still matches legacy orders by name when no partner is resolved', function () {
    $partner = User::factory()->create(['name' => 'Kiran', 'referral_code' => 'kiran']);

    metaAdOrder('meta-legacy-1')->forceFill([
        'utm_campaign' => 'Kiran Monsoon Push',
        'status' => DonationOrder::STATUS_PAID,
    ])->save();

    $count = DonationOrder::query()
        ->tap(fn ($query) => AdminStaffReferralsData::applyPartnerAttributionFilter($query, $partner))
        ->count();

    expect($count)->toBe(1);
});

it('builds meta url parameters with sid as the referral code', function () {
    $parameters = StaffReferral::metaAdParameters('zmupe', 'Siddharth');

    expect($parameters)->toContain('sid=zmupe')
        ->and($parameters)->toContain('utm_source=meta')
        ->and($parameters)->toContain('utm_medium=siddharth')
        ->and($parameters)->toContain('utm_campaign={{campaign.name}}')
        ->and($parameters)->toContain('utm_content={{ad.name}}')
        ->and($parameters)->toContain('utm_id={{campaign.id}}')
        ->and($parameters)->toContain('utm_term={{adset.id}}')
        ->and($parameters)->not->toContain('sid={{adset.id}}')
        ->and($parameters)->not->toContain('%7B');

    expect(StaffReferral::metaAdUrl('https://sadbhavnadham.org/donate/old-age-home', 'zmupe', 'Siddharth'))
        ->toStartWith('https://sadbhavnadham.org/donate/old-age-home?utm_source=meta')
        ->and(StaffReferral::metaAdUrl('https://sadbhavnadham.org/donate/old-age-home', 'zmupe', 'Siddharth'))
        ->toContain('sid=zmupe');
});

it('resolves the partner from sid on a live meta click url', function () {
    $partner = User::factory()->create(['name' => 'Siddharth', 'referral_code' => 'zmupe']);
    $order = metaAdOrder('meta-sid-1');

    $request = Request::create(
        '/donate/old-age-home?utm_source=meta&utm_medium=siddharth'
        .'&utm_campaign=Pritesh+-+1908+Old+age+home'
        .'&utm_content=100+Rs+bhojan+seva+-+Gujarat'
        .'&sid=zmupe&amt=100&ptype=et'
        .'&utm_id=120252324315880236&utm_term=120252324315890236',
        'POST'
    );

    app(AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->partner_user_id)->toBe($partner->id)
        ->and($order->partner_code)->toBe('zmupe')
        ->and($order->meta_campaign_id)->toBe('120252324315880236')
        ->and($order->meta_adset_id)->toBe('120252324315890236');
});

it('still accepts a leftover pid when sid is missing', function () {
    $partner = User::factory()->create(['referral_code' => 'ashvini']);
    $order = metaAdOrder('meta-legacy-pid');

    $request = Request::create(
        '/donate/tree-plantation?utm_source=meta&pid=ashvini&utm_id=120211&sid=120212',
        'POST'
    );

    app(AnalyticsService::class)->trackCheckoutStarted($request, $order);

    $order->refresh();

    expect($order->partner_user_id)->toBe($partner->id)
        ->and($order->partner_code)->toBe('ashvini')
        ->and($order->meta_adset_id)->toBe('120212');
});
