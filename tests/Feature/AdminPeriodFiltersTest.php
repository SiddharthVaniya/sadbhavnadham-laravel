<?php

use App\Models\DonationOrder;
use App\Models\User;
use App\Support\AdminAnalyticsData;
use App\Support\MarketerPerformanceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

function createPeriodFilterAdmin(array $permissions = ['view analytics', 'view donations', 'view all donations']): User
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('exposes calendar period options on the analytics page', function () {
    $user = createPeriodFilterAdmin(['view analytics']);

    actingAs($user)
        ->get(route('admin.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Analytics/Index')
            ->where('durationOptions.yesterday', 'Yesterday')
            ->where('durationOptions.this_week', 'This week')
            ->where('durationOptions.last_week', 'Previous week')
            ->where('durationOptions.this_month', 'This month')
            ->where('durationOptions.last_month', 'Previous month'));
});

it('includes calendar keys in analytics duration options constant', function () {
    expect(AdminAnalyticsData::DURATION_OPTIONS)->toHaveKeys([
        'yesterday',
        'this_week',
        'last_week',
        'this_month',
        'last_month',
    ]);
});

it('filters donations list to yesterday paid activity only', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:00'));

    $user = createPeriodFilterAdmin(['view donations', 'view all donations']);

    $yesterday = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'yesterday-order',
        'donor_name' => 'Yesterday Donor',
        'donor_email' => 'yesterday@example.com',
        'donor_phone' => '9999999901',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::parse('2026-09-15 10:00:00'),
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'today-order',
        'donor_name' => 'Today Donor',
        'donor_email' => 'today@example.com',
        'donor_phone' => '9999999902',
        'currency' => 'INR',
        'total_amount' => 700,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::parse('2026-09-16 09:00:00'),
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'last-week-order',
        'donor_name' => 'Last Week Donor',
        'donor_email' => 'lastweek@example.com',
        'donor_phone' => '9999999903',
        'currency' => 'INR',
        'total_amount' => 300,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::parse('2026-09-10 09:00:00'),
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'yesterday']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->where('duration', 'yesterday')
            ->has('donations.data', 1)
            ->where('donations.data.0.id', $yesterday->id)
            ->where('durationOptions.yesterday', 'Yesterday')
            ->where('durationOptions.this_week', 'This week')
            ->where('durationOptions.last_week', 'Previous week'));
});

it('filters donations list to this week paid activity only', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:00')); // Wed; week starts Mon 14

    $user = createPeriodFilterAdmin(['view donations', 'view all donations']);

    $inWeek = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'this-week-order',
        'donor_name' => 'This Week Donor',
        'donor_email' => 'thisweek@example.com',
        'donor_phone' => '9999999904',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::parse('2026-09-15 10:00:00'),
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'prior-week-order',
        'donor_name' => 'Prior Week Donor',
        'donor_email' => 'priorweek@example.com',
        'donor_phone' => '9999999905',
        'currency' => 'INR',
        'total_amount' => 400,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::parse('2026-09-13 10:00:00'),
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'this_week']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->where('duration', 'this_week')
            ->has('donations.data', 1)
            ->where('donations.data.0.id', $inWeek->id));
});

it('exposes this week and previous week on marketer duration options', function () {
    expect(MarketerPerformanceData::DURATION_OPTIONS)->toHaveKeys(['this_week', 'last_week'])
        ->and(MarketerPerformanceData::DURATION_OPTIONS['this_week'])->toBe('This week')
        ->and(MarketerPerformanceData::DURATION_OPTIONS['last_week'])->toBe('Previous week');
});
