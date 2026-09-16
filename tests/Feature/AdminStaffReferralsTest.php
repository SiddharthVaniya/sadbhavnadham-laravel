<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use App\Support\AdminNavigation;
use App\Support\AdminStaffReferralsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function referralsUser(array $permissions, ?string $referralCode = null): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    $role = Role::findOrCreate('referrals-tester-'.md5(implode(',', $permissions).($referralCode ?? '')));
    $role->syncPermissions($permissions);

    $user = User::factory()->create([
        'referral_code' => $referralCode,
    ]);
    $user->assignRole($role);

    return $user->fresh();
}

it('shows partner attribution in navigation for permitted users', function () {
    $user = referralsUser(['view staff referrals'], 'mjv');

    $labels = collect(AdminNavigation::build($user))->pluck('label')->all();

    expect($labels)->toContain('Partner Reports')
        ->and($labels)->toContain('Partner attribution');
});

it('lets a staff user see only donations for their referral code', function () {
    $staff = referralsUser(['view staff referrals'], 'mjv');

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-mjv-1',
        'donor_name' => 'MJV Donor',
        'donor_email' => 'mjv@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'mjv',
        'utm_source' => 'staff',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-paz-1',
        'donor_name' => 'PAZ Donor',
        'donor_email' => 'paz@example.com',
        'donor_phone' => '9876543211',
        'currency' => 'INR',
        'total_amount' => 5000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'paz',
        'utm_source' => 'staff',
    ]);

    actingAs($staff)
        ->get(route('admin.referrals.index', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Referrals/Index')
            ->where('code', 'mjv')
            ->where('can_view_all', false)
            ->where('summary.paid_orders', 1)
            ->where('summary.revenue', 1000)
            ->where('donations.meta.total', 1)
            ->has('donations.data', 1)
            ->where('donations.data.0.donor_name', 'MJV Donor'));
});

it('blocks staff from viewing another users referral code', function () {
    $staff = referralsUser(['view staff referrals'], 'mjv');

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-paz-only',
        'donor_name' => 'PAZ Donor',
        'donor_email' => 'paz2@example.com',
        'donor_phone' => '9876543212',
        'currency' => 'INR',
        'total_amount' => 2500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'paz',
        'utm_source' => 'staff',
    ]);

    actingAs($staff)
        ->get(route('admin.referrals.index', ['duration' => 'all', 'code' => 'paz']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('code', 'mjv')
            ->where('summary.paid_orders', 0)
            ->where('donations.meta.total', 0));
});

it('lets admins view all staff referral totals and filter by code', function () {
    $admin = referralsUser([
        'view staff referrals',
        'view all donations',
    ], 'admin-code');

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-admin-mjv',
        'donor_name' => 'MJV Donor',
        'donor_email' => 'mjv3@example.com',
        'donor_phone' => '9876543213',
        'currency' => 'INR',
        'total_amount' => 700,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'mjv',
        'utm_source' => 'staff',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-admin-paz',
        'donor_name' => 'PAZ Donor',
        'donor_email' => 'paz3@example.com',
        'donor_phone' => '9876543214',
        'currency' => 'INR',
        'total_amount' => 300,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'paz',
        'utm_source' => 'staff',
    ]);

    actingAs($admin)
        ->get(route('admin.referrals.index', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_view_all', true)
            ->where('summary.paid_orders', 2)
            ->where('summary.revenue', 1000)
            ->has('leaderboard', 2)
            ->has('filter_options.partners')
            ->has('filter_options.causes'));

    actingAs($admin)
        ->get(route('admin.referrals.index', ['duration' => 'all', 'code' => 'paz']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('code', 'paz')
            ->where('summary.paid_orders', 1)
            ->where('summary.revenue', 300));
});

it('filters partner attribution by a custom date range', function () {
    $admin = referralsUser([
        'view staff referrals',
        'view all donations',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-custom-in',
        'donor_name' => 'In Range Donor',
        'donor_email' => 'inrange@example.com',
        'donor_phone' => '9876543220',
        'currency' => 'INR',
        'total_amount' => 900,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::parse('2026-03-15 10:00:00'),
        'utm_content' => 'mjv',
        'utm_source' => 'staff',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-custom-out',
        'donor_name' => 'Out Range Donor',
        'donor_email' => 'outrange@example.com',
        'donor_phone' => '9876543221',
        'currency' => 'INR',
        'total_amount' => 400,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::parse('2026-01-10 10:00:00'),
        'utm_content' => 'mjv',
        'utm_source' => 'staff',
    ]);

    actingAs($admin)
        ->get(route('admin.referrals.index', [
            'duration' => 'custom',
            'from_date' => '2026-03-01',
            'to_date' => '2026-03-31',
            'code' => 'mjv',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('duration.key', 'custom')
            ->where('duration.from_date', '2026-03-01')
            ->where('duration.to_date', '2026-03-31')
            ->where('summary.paid_orders', 1)
            ->where('summary.revenue', 900)
            ->where('donations.meta.total', 1));
});

it('filters by partner user cause status and match type', function () {
    $admin = referralsUser([
        'view staff referrals',
        'view all donations',
    ]);
    $partner = User::factory()->create([
        'name' => 'Partner MJV',
        'referral_code' => 'mjv',
    ]);
    $tree = Cause::factory()->create(['title' => 'Tree Plantation', 'is_active' => true]);
    $home = Cause::factory()->create(['title' => 'Old Age Home', 'is_active' => true]);

    $partnerOrder = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-partner-tree',
        'donor_name' => 'Partner Tree Donor',
        'donor_email' => 'partnertree@example.com',
        'donor_phone' => '9876543230',
        'currency' => 'INR',
        'total_amount' => 800,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'mjv',
        'utm_source' => 'staff',
        'utm_campaign' => 'spring',
    ]);

    DonationItem::create([
        'donation_order_id' => $partnerOrder->id,
        'cause_id' => $tree->id,
        'cause' => $tree->slug,
        'title' => 'Tree',
        'quantity' => 1,
        'unit_amount' => 800,
        'amount' => 800,
    ]);

    $campaignOrder = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-campaign-title',
        'donor_name' => 'Campaign Donor',
        'donor_email' => 'campaign@example.com',
        'donor_phone' => '9876543231',
        'currency' => 'INR',
        'total_amount' => 1200,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => '27/07 | Sadbhavna Video',
        'utm_source' => 'facebook',
        'utm_campaign' => 'video_ads',
    ]);

    DonationItem::create([
        'donation_order_id' => $campaignOrder->id,
        'cause_id' => $home->id,
        'cause' => $home->slug,
        'title' => 'Meal',
        'quantity' => 1,
        'unit_amount' => 1200,
        'amount' => 1200,
    ]);

    actingAs($admin)
        ->get(route('admin.referrals.index', [
            'duration' => 'all',
            'partner_user_id' => $partner->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('partner_user_id', $partner->id)
            ->where('code', 'mjv')
            ->where('summary.paid_orders', 1)
            ->where('summary.revenue', 800));

    actingAs($admin)
        ->get(route('admin.referrals.index', [
            'duration' => 'all',
            'cause_id' => $home->id,
            'match' => 'unmatched',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.paid_orders', 1)
            ->where('summary.revenue', 1200)
            ->where('donations.data.0.donor_name', 'Campaign Donor'));

    actingAs($admin)
        ->get(route('admin.referrals.index', [
            'duration' => 'all',
            'match' => 'partners',
            'utm_source' => 'staff',
            'status' => 'paid',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.paid_orders', 1)
            ->where('code', null)
            ->where('donations.data.0.donor_name', 'Partner Tree Donor'));
});

it('attributes meta facebook donations to partner by name in utm fields', function () {
    $admin = referralsUser([
        'view staff referrals',
        'view all donations',
    ]);

    $ashvini = User::factory()->create([
        'name' => 'Ashvini',
        'referral_code' => 'ac',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ref-meta-ashvini',
        'donor_name' => 'Monil Vekariya',
        'donor_email' => 'vekariyamonil8@gmail.com',
        'donor_phone' => '9876543299',
        'currency' => 'INR',
        'total_amount' => 10,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_source' => 'meta',
        'utm_medium' => 'Facebook_Desktop_Feed',
        'utm_campaign' => '12/08 | Sadbhavna | Shravan | Ashvini',
        'utm_content' => '12/08 | Sadbhavna | Shravan | Ashvini | Old age 1000',
    ]);

    actingAs($admin)
        ->get(route('admin.referrals.index', [
            'duration' => 'this_month',
            'partner_user_id' => $ashvini->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('partner_user_id', $ashvini->id)
            ->where('code', 'ac')
            ->where('summary.paid_orders', 1)
            ->where('summary.revenue', 10)
            ->where('donations.meta.total', 1)
            ->where('donations.data.0.donor_name', 'Monil Vekariya'));

    Permission::findOrCreate('view staff referrals');
    $staffRole = Role::findOrCreate('ashvini-staff');
    $staffRole->syncPermissions(['view staff referrals']);
    $ashvini->assignRole($staffRole);

    actingAs($ashvini->fresh())
        ->get(route('admin.referrals.index', ['duration' => 'this_month']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('code', 'ac')
            ->where('summary.paid_orders', 1)
            ->where('summary.revenue', 10));
});

it('forbids users without the staff referrals permission', function () {
    $user = referralsUser(['view donations']);

    actingAs($user)
        ->get(route('admin.referrals.index'))
        ->assertForbidden();
});

it('resolves own referral scope for non admins', function () {
    $user = referralsUser(['view staff referrals'], 'mjv');

    expect(AdminStaffReferralsData::resolveScopeCode($user, 'paz'))->toBe('mjv')
        ->and(AdminStaffReferralsData::userCanViewAll($user))->toBeFalse();
});
