<?php

use App\Models\AnalyticsDailyCause;
use App\Models\AnalyticsDailyDimension;
use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSessionDay;
use App\Models\Cause;
use App\Models\User;
use App\Services\AnalyticsRollupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createRollupAnalyticsAdmin(): User
{
    Permission::firstOrCreate(['name' => 'view analytics']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view analytics');

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function seedRollupEvents(Cause $cause): void
{
    $today = now();
    $yesterday = now()->subDay();

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_CAUSE,
        'session_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        'cause_id' => $cause->id,
        'path' => '/donate/'.$cause->slug,
        'referrer' => 'https://m.facebook.com/page',
        'device_type' => 'mobile',
        'country_code' => 'IN',
        'country_name' => 'India',
        'region_name' => 'Gujarat',
        'city' => 'Ahmedabad',
        'created_at' => $yesterday->copy()->setTime(10, 0),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_CAUSE,
        'session_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        'cause_id' => $cause->id,
        'path' => '/donate/'.$cause->slug,
        'referrer' => 'https://www.facebook.com/another',
        'device_type' => 'mobile',
        'country_code' => 'IN',
        'country_name' => 'India',
        'region_name' => 'Gujarat',
        'city' => 'Ahmedabad',
        'created_at' => $today->copy()->setTime(11, 0),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_CAUSE,
        'session_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        'cause_id' => $cause->id,
        'path' => '/donate/'.$cause->slug,
        'referrer' => 'https://google.com/',
        'device_type' => 'desktop',
        'country_code' => 'US',
        'country_name' => 'United States',
        'region_name' => 'California',
        'city' => 'Mountain View',
        'created_at' => $today->copy()->setTime(12, 0),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_CHECKOUT_STARTED,
        'session_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        'cause_id' => $cause->id,
        'amount' => 500,
        'country_code' => 'IN',
        'created_at' => $today->copy()->setTime(12, 30),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_DONATION_PAID,
        'cause_id' => $cause->id,
        'amount' => 500,
        'country_code' => 'IN',
        'created_at' => $today->copy()->setTime(12, 45),
    ]);
}

it('rolls up daily stats causes referrers and sessions', function () {
    $cause = Cause::factory()->create();
    seedRollupEvents($cause);

    Artisan::call('analytics:rollup', [
        '--from' => now()->subDay()->toDateString(),
        '--to' => now()->toDateString(),
    ]);

    expect(AnalyticsDailyStat::query()->where('scope', 'all')->count())->toBe(2);
    expect(AnalyticsDailyStat::query()->where('scope', 'in')->count())->toBe(2);

    $todayAll = AnalyticsDailyStat::query()
        ->where('scope', 'all')
        ->whereDate('stat_date', now()->toDateString())
        ->first();

    expect($todayAll)->not->toBeNull();
    expect($todayAll->cause_views)->toBe(2);
    expect($todayAll->unique_visitors)->toBe(2);
    expect($todayAll->checkouts_started)->toBe(1);
    expect($todayAll->donations_paid)->toBe(1);
    expect((float) $todayAll->tracked_revenue)->toBe(500.0);

    expect(
        AnalyticsDailyCause::query()
            ->where('scope', 'all')
            ->where('cause_id', $cause->id)
            ->sum('views')
    )->toBe(3);

    expect(
        AnalyticsDailyDimension::query()
            ->where('scope', 'all')
            ->where('dimension_type', AnalyticsDailyDimension::TYPE_REFERRER)
            ->where('dimension_key', 'facebook.com')
            ->sum('hits')
    )->toBe(2);

    expect(
        AnalyticsSessionDay::query()
            ->where('scope', 'all')
            ->distinct()
            ->count('session_id')
    )->toBe(2);
});

it('is idempotent when rolling up the same day twice', function () {
    $cause = Cause::factory()->create();
    seedRollupEvents($cause);

    $service = app(AnalyticsRollupService::class);
    $service->rollupDay(now());
    $service->rollupDay(now());

    expect(AnalyticsDailyStat::query()->where('scope', 'all')->whereDate('stat_date', now()->toDateString())->count())->toBe(1);
    expect(AnalyticsSessionDay::query()->where('scope', 'all')->whereDate('stat_date', now()->toDateString())->count())->toBe(2);
});

it('serves the analytics dashboard from rollups after backfill', function () {
    config(['analytics.admin_report_cache_ttl' => 0]);

    $user = createRollupAnalyticsAdmin();
    $cause = Cause::factory()->create();
    seedRollupEvents($cause);

    Artisan::call('analytics:rollup', [
        '--from' => now()->subDays(6)->toDateString(),
        '--to' => now()->toDateString(),
    ]);

    actingAs($user)
        ->get(route('admin.analytics.index', ['duration' => '7d']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Analytics/Index')
            ->where('summary.cause_views', 3)
            ->where('summary.unique_visitors', 3)
            ->where('summary.checkouts_started', 1)
            ->where('summary.donations_paid', 1)
            ->where('referrers.0.referrer', 'facebook.com')
            ->where('referrers.0.hits', 2)
            ->has('locations.countries', 2));
});

it('applies india focus using in-scope rollups', function () {
    config(['analytics.admin_report_cache_ttl' => 0]);

    $user = createRollupAnalyticsAdmin();
    $cause = Cause::factory()->create();
    seedRollupEvents($cause);

    Artisan::call('analytics:rollup', [
        '--from' => now()->subDays(6)->toDateString(),
        '--to' => now()->toDateString(),
    ]);

    actingAs($user)
        ->get(route('admin.analytics.index', ['duration' => '7d', 'india_focus' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.cause_views', 2)
            ->where('summary.unique_visitors', 2)
            ->where('summary.countries_reached', 1)
            ->where('locations.countries.0.label', 'India'));
});

it('merges unicode-equivalent region names without duplicate key errors', function () {
    $cause = Cause::factory()->create();

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_CAUSE,
        'session_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
        'cause_id' => $cause->id,
        'path' => '/donate/'.$cause->slug,
        'region_name' => 'Baladīyat ad Dawḩah',
        'country_code' => 'QA',
        'country_name' => 'Qatar',
        'created_at' => now(),
    ]);

    AnalyticsEvent::create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_CAUSE,
        'session_id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        'cause_id' => $cause->id,
        'path' => '/donate/'.$cause->slug,
        'region_name' => 'Baladiyat ad Dawhah',
        'country_code' => 'QA',
        'country_name' => 'Qatar',
        'created_at' => now(),
    ]);

    app(AnalyticsRollupService::class)->rollupDay(now());

    $regionRows = AnalyticsDailyDimension::query()
        ->where('scope', 'all')
        ->where('dimension_type', AnalyticsDailyDimension::TYPE_REGION)
        ->whereDate('stat_date', now()->toDateString())
        ->get();

    expect($regionRows)->toHaveCount(1);
    expect((int) $regionRows->first()->hits)->toBe(2);
    expect((int) $regionRows->first()->visitors)->toBe(2);
});
