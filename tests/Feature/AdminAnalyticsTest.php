<?php

use App\Models\AnalyticsEvent;
use App\Models\Cause;
use App\Models\DonationOrder;
use App\Models\User;
use App\Services\AnalyticsGeoLocator;
use App\Services\AnalyticsService;
use App\Services\DonationPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createAnalyticsAdmin(): User
{
    Permission::firstOrCreate(['name' => 'view analytics']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view analytics');

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function createPendingOrder(Cause $cause, array $attributes = []): DonationOrder
{
    $order = DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order-'.fake()->unique()->bothify('####'),
        'donor_name' => 'Test Donor',
        'donor_email' => 'test@example.com',
        'donor_phone' => '9999999999',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PENDING,
    ], $attributes));

    $order->items()->create([
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Test item',
        'quantity' => 1,
        'unit_amount' => $order->total_amount,
        'amount' => $order->total_amount,
    ]);

    return $order;
}

it('tracks public donate page visits', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'query' => '8.8.8.8',
            'country' => 'United States',
            'countryCode' => 'US',
            'regionName' => 'California',
            'city' => 'Mountain View',
        ]),
    ]);

    $cause = Cause::factory()->create(['is_active' => true]);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->get(route('donate.index'))
        ->assertOk();

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->get(route('donate.show', $cause))
        ->assertOk();

    expect(AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_VISIT_HOME)->count())->toBe(1);
    expect(AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_VISIT_CAUSE)->count())->toBe(1);

    $visit = AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_VISIT_HOME)->first();

    expect($visit->country_code)->toBe('US');
    expect($visit->country_name)->toBe('United States');
    expect($visit->region_name)->toBe('California');
    expect($visit->city)->toBe('Mountain View');
});

it('stores location on checkout and copies it to paid donation events', function () {
    Queue::fake();

    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'query' => '103.21.244.0',
            'country' => 'India',
            'countryCode' => 'IN',
            'regionName' => 'Gujarat',
            'city' => 'Ahmedabad',
        ]),
    ]);

    $cause = Cause::factory()->create();
    $order = createPendingOrder($cause, ['provider_order_id' => 'order-geo']);

    $request = request()->duplicate(server: ['REMOTE_ADDR' => '103.21.244.0']);

    app(AnalyticsService::class)->trackCheckoutStarted($request, $order);

    app(DonationPaymentService::class)->handleCaptured([
        'order_id' => 'order-geo',
        'id' => 'pay_geo',
    ]);

    $paidEvent = AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_DONATION_PAID)->first();

    expect($paidEvent)->not->toBeNull();
    expect($paidEvent->country_code)->toBe('IN');
    expect($paidEvent->city)->toBe('Ahmedabad');
    expect($paidEvent->region_name)->toBe('Gujarat');
});

it('resolves visitor location from cloudflare country header when ip lookup is unavailable', function () {
    $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_CF_IPCOUNTRY' => 'IN',
    ]);

    $location = app(AnalyticsGeoLocator::class)->fromRequest($request);

    expect($location['country_code'])->toBe('IN');
    expect($location['country_name'])->toBe('India');
});

it('tracks checkout started events', function () {
    $cause = Cause::factory()->create();
    $order = createPendingOrder($cause);

    app(AnalyticsService::class)->trackCheckoutStarted(request(), $order);

    expect(AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_CHECKOUT_STARTED)->count())->toBe(1);
});

it('tracks paid and failed donation events from payment service', function () {
    Queue::fake();

    $cause = Cause::factory()->create();
    $order = createPendingOrder($cause, ['provider_order_id' => 'order-paid']);

    app(DonationPaymentService::class)->handleCaptured([
        'order_id' => 'order-paid',
        'id' => 'pay_test',
    ]);

    expect(AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_DONATION_PAID)->count())->toBe(1);

    $failedOrder = createPendingOrder($cause, ['provider_order_id' => 'order-failed']);

    app(DonationPaymentService::class)->handleFailed([
        'order_id' => 'order-failed',
    ]);

    expect(AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_DONATION_FAILED)->count())->toBe(1);
});

it('renders analytics dashboard with summary metrics', function () {
    $user = createAnalyticsAdmin();
    $cause = Cause::factory()->create();

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_CAUSE,
        'session_id' => '11111111-1111-1111-1111-111111111111',
        'cause_id' => $cause->id,
        'path' => '/donate/'.$cause->slug,
        'referrer' => 'https://m.facebook.com/page',
        'country_code' => 'IN',
        'country_name' => 'India',
        'region_name' => 'Gujarat',
        'city' => 'Ahmedabad',
        'created_at' => now(),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_CAUSE,
        'session_id' => '11111111-1111-1111-1111-111111111111',
        'cause_id' => $cause->id,
        'path' => '/donate/'.$cause->slug,
        'referrer' => 'https://www.facebook.com/another',
        'created_at' => now(),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_CHECKOUT_STARTED,
        'session_id' => '11111111-1111-1111-1111-111111111111',
        'cause_id' => $cause->id,
        'amount' => 500,
        'created_at' => now(),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_DONATION_PAID,
        'cause_id' => $cause->id,
        'amount' => 500,
        'created_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Analytics/Index')
            ->where('duration', 'today')
            ->where('durationLabel', 'Today')
            ->where('summary.cause_views', 2)
            ->where('summary.checkouts_started', 1)
            ->where('summary.donations_paid', 1)
            ->where('summary.countries_reached', 1)
            ->where('referrers.0.referrer', 'facebook.com')
            ->where('referrers.0.hits', 2)
            ->has('topCauses', 1)
            ->has('comparison.metrics', 4)
            ->has('locations.countries', 1)
            ->where('locations.countries.0.label', 'India')
            ->has('locations.cities', 1));
});

it('counts abandoned checkouts without loading all order ids into php', function () {
    $user = createAnalyticsAdmin();
    $cause = Cause::factory()->create();

    $abandoned = createPendingOrder($cause, [
        'provider_order_id' => 'order-abandoned',
        'total_amount' => 750,
    ]);
    $paid = createPendingOrder($cause, [
        'provider_order_id' => 'order-paid-checkout',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_CHECKOUT_STARTED,
        'donation_order_id' => $abandoned->id,
        'cause_id' => $cause->id,
        'amount' => 750,
        'created_at' => now(),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_CHECKOUT_STARTED,
        'donation_order_id' => $paid->id,
        'cause_id' => $cause->id,
        'amount' => 500,
        'created_at' => now(),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_DONATION_PAID,
        'donation_order_id' => $paid->id,
        'cause_id' => $cause->id,
        'amount' => 500,
        'created_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.analytics.index', ['duration' => 'today']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('abandonedCheckouts.count', 1)
            ->where('abandonedCheckouts.amount', 750)
            ->has('abandonedCheckouts.orders', 1)
            ->where('abandonedCheckouts.orders.0.donor_name', 'Test Donor'));
});

it('serves cached analytics reports for repeated requests', function () {
    config(['analytics.admin_report_cache_ttl' => 300]);

    $user = createAnalyticsAdmin();

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_HOME,
        'session_id' => '22222222-2222-2222-2222-222222222222',
        'path' => '/',
        'created_at' => now(),
    ]);

    actingAs($user)->get(route('admin.analytics.index', ['duration' => 'today']))->assertOk();

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_HOME,
        'session_id' => '33333333-3333-3333-3333-333333333333',
        'path' => '/',
        'created_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.analytics.index', ['duration' => 'today']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.unique_visitors', 1));
});

it('exports analytics csv for authorized admins', function () {
    $user = createAnalyticsAdmin();

    $response = actingAs($user)->get(route('admin.analytics.export', ['duration' => 'today']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('unique_visitors');
});

it('blocks analytics page without permission', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('admin.analytics.index'))
        ->assertForbidden();
});
