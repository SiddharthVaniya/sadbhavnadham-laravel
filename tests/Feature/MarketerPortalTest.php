<?php

use App\Models\DonationOrder;
use App\Models\LinkTrackingSummary;
use App\Models\LinkTrackingVisit;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function digitalMarketer(array $overrides = []): User
{
    Role::firstOrCreate(['name' => 'digital_marketer', 'guard_name' => 'web']);

    foreach ([
        AdminPermissions::CAUSE_VIEW,
        AdminPermissions::CAUSE_COPY_LINKS,
        AdminPermissions::CAMPAIGN_VIEW,
        AdminPermissions::CAMPAIGN_COPY_LINKS,
        AdminPermissions::PACKAGE_VIEW,
        AdminPermissions::PACKAGE_COPY_LINKS,
        AdminPermissions::REFERRAL_VIEW,
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $user = User::factory()->withReferralCode($overrides['referral_code'] ?? 'ashvini')->create([
        'name' => $overrides['name'] ?? 'Ashvini',
        'email' => $overrides['email'] ?? 'ashvini@example.com',
        'password' => $overrides['password'] ?? 'password',
        'donation_target' => $overrides['donation_target'] ?? null,
    ]);

    $user->assignRole('digital_marketer');

    return $user;
}

function marketerVisit(User $marketer, array $overrides = []): LinkTrackingVisit
{
    $code = (string) $marketer->referral_code;

    return LinkTrackingVisit::query()->create(array_merge([
        'visitor_id' => (string) Illuminate\Support\Str::uuid(),
        'sid' => $code,
        'utm_source' => 'meta',
        'utm_medium' => 'ashvini',
        'utm_campaign' => 'Monsoon Trees',
        'utm_content' => 'Plant a tree',
        'utm_id' => '120211',
        'utm_term' => '120212',
        'page_path' => '/donate/tree-plantation',
        'landing_url' => 'https://sadbhavnadham.org/donate/tree-plantation?sid='.$code,
        'extra_params' => null,
        'device_type' => 'mobile',
        'is_unique' => true,
        'converted' => false,
    ], $overrides));
}

it('sends a digital marketer to the marketer panel after login', function () {
    $user = digitalMarketer();

    $this->from(route('login'))
        ->post(route('admin.login.submit'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('marketer.dashboard'));
});

it('keeps a logged-in digital marketer off the admin panel', function () {
    $user = digitalMarketer();

    actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('marketer.dashboard'));

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('marketer.dashboard'));
});

it('shares a marketer theme only on marketer portal pages', function () {
    $marketer = digitalMarketer();

    actingAs($marketer)
        ->get(route('marketer.campaigns'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Campaigns')
            ->where('portal.label', 'Marketer')
            ->where('portal.home', '/marketer'));

    Permission::firstOrCreate(['name' => AdminPermissions::ANALYTICS_VIEW]);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(AdminPermissions::ANALYTICS_VIEW);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('portal.label', 'Admin')
            ->where('portal.home', '/admin'));
});

it('lets an admin still use the admin panel and blocks them from the marketer panel', function () {
    Permission::firstOrCreate(['name' => AdminPermissions::ANALYTICS_VIEW]);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(AdminPermissions::ANALYTICS_VIEW);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();

    actingAs($admin)
        ->get(route('marketer.dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

it('shows only the logged-in marketer performance on the dashboard', function () {
    $ashvini = digitalMarketer(['referral_code' => 'ashvini', 'email' => 'ashvini@example.com']);
    $kiran = digitalMarketer(['referral_code' => 'kiran', 'email' => 'kiran@example.com', 'name' => 'Kiran']);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-paid-1',
        'donor_name' => 'Tree Donor',
        'donor_email' => 'tree@example.com',
        'donor_phone' => '9876500001',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'ashvini',
        'utm_campaign' => 'Monsoon Trees',
        'device_type' => 'mobile',
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-paid-kiran',
        'donor_name' => 'Other Donor',
        'donor_email' => 'other@example.com',
        'donor_phone' => '9876500002',
        'currency' => 'INR',
        'total_amount' => 5000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $kiran->id,
        'partner_code' => 'kiran',
        'utm_campaign' => 'Other',
        'device_type' => 'desktop',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.dashboard', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Dashboard')
            ->where('profile.code', 'ashvini')
            ->where('summary.donations', 1)
            ->where('summary.revenue', 1100)
            ->has('dailyTrend')
            ->has('campaignRevenueTrend')
            ->has('topCampaigns')
            ->has('donations.data', 1)
            ->where('donations.data.0.campaign', 'Monsoon Trees')
            ->where('target.goal', null));
});

it('shows admin rupee target progress from paid attributed donations', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'ashvini',
        'email' => 'ashvini-target@example.com',
        'donation_target' => 20000,
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-target-1',
        'donor_name' => 'Target Donor',
        'donor_email' => 'target@example.com',
        'donor_phone' => '9876500003',
        'currency' => 'INR',
        'total_amount' => 2000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'ashvini',
        'device_type' => 'mobile',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.dashboard', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Dashboard')
            ->where('target.goal', 20000)
            ->where('target.achieved', 2000)
            ->where('target.remaining', 18000)
            ->where('target.achieved_percent', 10)
            ->where('target.remaining_percent', 90)
            ->has('dailyTrend')
            ->has('topCampaigns'));
});

it('compares rupee target to collected amount not donation count', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'xvjsrg',
        'email' => 'ashvini-rupees@example.com',
        'donation_target' => 200,
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-rupees-1',
        'donor_name' => 'A',
        'donor_email' => 'a@example.com',
        'donor_phone' => '9876500004',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'xvjsrg',
    ]);
    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-rupees-2',
        'donor_name' => 'B',
        'donor_email' => 'b@example.com',
        'donor_phone' => '9876500005',
        'currency' => 'INR',
        'total_amount' => 1550,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'xvjsrg',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.dashboard', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Dashboard')
            ->where('target.goal', 200)
            ->where('target.achieved', 3050)
            ->where('target.remaining', 0)
            ->where('target.achieved_percent', 100)
            ->where('target.remaining_percent', 0)
            ->where('summary.donations', 2)
            ->where('summary.revenue', 3050));
});

it('shows charts on marketer campaigns and visits pages', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'ashvini',
        'email' => 'ashvini-charts@example.com',
        'donation_target' => 200,
    ]);

    marketerVisit($ashvini, [
        'converted' => true,
        'converted_amount' => 1100,
        'converted_at' => now(),
        'utm_campaign' => 'Feed',
        'utm_id' => '111',
        'device_type' => 'mobile',
    ]);
    marketerVisit($ashvini, [
        'visitor_id' => (string) Illuminate\Support\Str::uuid(),
        'page_path' => '/donate/stories',
        'utm_campaign' => 'Stories',
        'utm_id' => '222',
        'device_type' => 'desktop',
        'is_unique' => false,
    ]);

    LinkTrackingSummary::query()->create([
        'sid' => 'ashvini',
        'total_clicks' => 2,
        'unique_visitors' => 1,
        'total_donations' => 1,
        'total_amount' => 1100,
        'average_amount' => 1100,
        'utm_campaign' => 'Feed',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.campaigns', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Campaigns')
            ->where('target.goal', 200)
            ->where('target.achieved', 1100)
            ->where('target.remaining', 0)
            ->where('target.achieved_percent', 100)
            ->has('campaigns', 2));

    actingAs($ashvini)
        ->get(route('marketer.visits', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Visits')
            ->where('target.goal', 200)
            ->has('resultBreakdown', 3)
            ->has('visits.data', 2));
});

it('lists marketer campaigns and visits without other marketers data', function () {
    $ashvini = digitalMarketer(['referral_code' => 'ashvini', 'email' => 'ashvini2@example.com']);
    $kiran = digitalMarketer(['referral_code' => 'kiran', 'email' => 'kiran2@example.com', 'name' => 'Kiran']);

    marketerVisit($ashvini, ['utm_campaign' => 'Feed', 'utm_id' => '111', 'page_path' => '/donate/feed']);
    marketerVisit($ashvini, ['utm_campaign' => 'Stories', 'utm_id' => '222', 'page_path' => '/donate/stories']);
    marketerVisit($kiran, ['utm_campaign' => 'Other', 'utm_id' => '333']);

    LinkTrackingSummary::query()->create([
        'sid' => 'ashvini',
        'total_clicks' => 2,
        'unique_visitors' => 2,
        'total_donations' => 0,
        'total_amount' => 0,
        'average_amount' => 0,
        'utm_campaign' => 'Feed',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.campaigns', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Campaigns')
            ->has('campaigns', 2)
            ->where('campaigns', function ($campaigns) {
                $names = collect($campaigns)->pluck('utm_campaign')->sort()->values()->all();

                return $names === ['Feed', 'Stories'];
            }));

    actingAs($ashvini)
        ->get(route('marketer.visits', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Visits')
            ->has('visits.data', 2)
            ->where('visits.data.0.sid', 'ashvini')
            ->where('visits.data.1.sid', 'ashvini'));
});

it('shows utm source medium content referrer and extra params on marketer clicks', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'ashvini',
        'email' => 'ashvini-utm@example.com',
    ]);

    marketerVisit($ashvini, [
        'utm_source' => 'meta',
        'utm_medium' => 'Facebook_Desktop_Feed',
        'utm_campaign' => 'Tree Plantation',
        'utm_content' => 'Plant a tree',
        'referrer' => 'https://www.facebook.com/',
        'extra_params' => [
            'aid' => '120213',
            'placement' => 'Facebook_Desktop_Feed',
        ],
    ]);

    actingAs($ashvini)
        ->get(route('marketer.visits', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Visits')
            ->where('visits.data.0.utm_source', 'meta')
            ->where('visits.data.0.utm_medium', 'Facebook_Desktop_Feed')
            ->where('visits.data.0.utm_content', 'Plant a tree')
            ->where('visits.data.0.referrer', 'https://www.facebook.com/')
            ->where('visits.data.0.extra_params.aid', '120213')
            ->where('visits.data.0.extra_params.placement', 'Facebook_Desktop_Feed'));

    actingAs($ashvini)
        ->get(route('marketer.campaigns', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Campaigns')
            ->has('campaigns', 1)
            ->where('campaigns.0.utm_campaign', 'Tree Plantation')
            ->where('campaigns.0.clicks', 1));
});

it('groups marketer campaigns by campaign name for the selected period', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'xvjsrg',
        'email' => 'ashvini-lifetime@example.com',
    ]);

    marketerVisit($ashvini, [
        'utm_campaign' => 'Old Age Home',
        'utm_id' => '111',
        'utm_term' => 'aaa',
        'converted' => true,
        'converted_amount' => 50,
        'converted_at' => now(),
    ]);
    marketerVisit($ashvini, [
        'visitor_id' => (string) Illuminate\Support\Str::uuid(),
        'page_path' => '/donate/trees',
        'utm_campaign' => 'Tree Plantation',
        'utm_id' => '222',
        'utm_term' => 'bbb',
        'converted' => true,
        'converted_amount' => 3000,
        'converted_at' => now(),
    ]);

    $older = marketerVisit($ashvini, [
        'visitor_id' => (string) Illuminate\Support\Str::uuid(),
        'page_path' => '/donate/old-age-home/prior',
        'utm_campaign' => 'Old Age Home',
        'utm_id' => '111',
        'utm_term' => 'aaa',
        'converted' => true,
        'converted_amount' => 1,
        'converted_at' => now()->subMonths(3),
    ]);
    $older->created_at = now()->subMonths(3);
    $older->save();

    actingAs($ashvini)
        ->get(route('marketer.campaigns', ['duration' => '30d']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Campaigns')
            ->has('campaigns', 2)
            ->where('campaigns', function ($campaigns) {
                $rows = collect($campaigns);
                $oldAge = $rows->firstWhere('utm_campaign', 'Old Age Home');
                $trees = $rows->firstWhere('utm_campaign', 'Tree Plantation');

                return $oldAge
                    && $trees
                    && $oldAge['clicks'] === 1
                    && (float) $oldAge['revenue'] === 50.0
                    && $trees['clicks'] === 1
                    && (float) $trees['revenue'] === 3000.0;
            }));
});

it('filters marketer campaigns by campaign name, campaign id, and ad set id', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'xvjsrg',
        'email' => 'ashvini-campaign-filter@example.com',
    ]);

    marketerVisit($ashvini, [
        'utm_campaign' => 'Old Age Home',
        'utm_id' => '111',
        'utm_term' => 'aaa',
        'converted' => true,
        'converted_amount' => 50,
        'converted_at' => now(),
    ]);
    marketerVisit($ashvini, [
        'visitor_id' => (string) Illuminate\Support\Str::uuid(),
        'page_path' => '/donate/trees',
        'utm_campaign' => 'Tree Plantation',
        'utm_id' => '222',
        'utm_term' => 'bbb',
        'converted' => true,
        'converted_amount' => 3000,
        'converted_at' => now(),
    ]);
    marketerVisit($ashvini, [
        'visitor_id' => (string) Illuminate\Support\Str::uuid(),
        'page_path' => '/donate/trees/adset',
        'utm_campaign' => 'Tree Plantation',
        'utm_id' => '222',
        'utm_term' => 'ccc',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.campaigns', ['duration' => 'all', 'utm_id' => '111']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Campaigns')
            ->has('campaigns', 1)
            ->where('campaigns.0.utm_campaign', 'Old Age Home')
            ->where('campaigns.0.clicks', 1)
            ->where('campaigns.0.revenue', 50));

    actingAs($ashvini)
        ->get(route('marketer.campaigns', ['duration' => 'all', 'utm_campaign' => 'Tree Plantation']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Campaigns')
            ->has('campaigns', 1)
            ->where('campaigns.0.utm_campaign', 'Tree Plantation')
            ->where('campaigns.0.clicks', 2));

    actingAs($ashvini)
        ->get(route('marketer.campaigns', ['duration' => 'all', 'utm_term' => 'ccc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Campaigns')
            ->has('campaigns', 1)
            ->where('campaigns.0.utm_campaign', 'Tree Plantation')
            ->where('campaigns.0.clicks', 1)
            ->where('campaigns.0.revenue', 0));
});

it('filters marketer dashboard donations by a custom start and end date', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'xvjsrg',
        'email' => 'ashvini-date-filter@example.com',
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-inside-window',
        'donor_name' => 'Inside',
        'donor_email' => 'inside@example.com',
        'donor_phone' => '9876500006',
        'currency' => 'INR',
        'total_amount' => 700,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::parse('2026-08-10 10:00:00'),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'xvjsrg',
        'utm_campaign' => 'Inside Window',
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-outside-window',
        'donor_name' => 'Outside',
        'donor_email' => 'outside@example.com',
        'donor_phone' => '9876500007',
        'currency' => 'INR',
        'total_amount' => 400,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::parse('2026-07-01 10:00:00'),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'xvjsrg',
        'utm_campaign' => 'Outside Window',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.dashboard', [
            'duration' => 'custom',
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-20',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Dashboard')
            ->where('summary.donations', 1)
            ->where('summary.revenue', 700)
            ->where('filters.from_date', '2026-08-01')
            ->where('filters.to_date', '2026-08-20')
            ->where('donations.data.0.campaign', 'Inside Window')
            ->where('durationLabel', fn (string $label) => str_contains($label, '01 Aug 2026') && str_contains($label, '20 Aug 2026')));
});

it('filters marketer visits by source medium content device and result', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'xvjsrg',
        'email' => 'ashvini-all-filters@example.com',
    ]);

    marketerVisit($ashvini, [
        'utm_source' => 'meta',
        'utm_medium' => 'Instagram_Feed',
        'utm_campaign' => 'Ghau Dan',
        'utm_content' => 'Retargeting',
        'device_type' => 'mobile',
        'converted' => true,
        'converted_amount' => 500,
        'converted_at' => now(),
        'page_path' => '/donate/old-age-home',
        'referrer' => 'https://www.facebook.com/',
        'extra_params' => ['aid' => '120213'],
    ]);
    marketerVisit($ashvini, [
        'visitor_id' => (string) Illuminate\Support\Str::uuid(),
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'Search',
        'utm_content' => 'Brand',
        'device_type' => 'desktop',
        'page_path' => '/donate/trees',
        'referrer' => 'https://www.google.com/',
        'extra_params' => ['aid' => '999'],
    ]);

    actingAs($ashvini)
        ->get(route('marketer.visits', [
            'duration' => 'all',
            'utm_source' => 'meta',
            'utm_medium' => 'Instagram_Feed',
            'utm_content' => 'Retargeting',
            'device_type' => 'mobile',
            'result' => 'donated',
            'page_path' => '/donate/old-age-home',
            'referrer' => 'https://www.facebook.com/',
            'aid' => '120213',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Visits')
            ->has('visits.data', 1)
            ->where('visits.data.0.utm_source', 'meta')
            ->where('visits.data.0.device_type', 'mobile')
            ->where('visits.data.0.converted', true)
            ->where('filters.utm_source', 'meta')
            ->where('filters.result', 'donated')
            ->where('filters.aid', '120213'));
});

it('exposes yesterday and this month duration options on marketer pages', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'ashvini',
        'email' => 'ashvini-duration@example.com',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.donations', ['duration' => '30d']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Donations')
            ->where('durationOptions.yesterday', 'Yesterday')
            ->where('durationOptions.this_week', 'This week')
            ->where('durationOptions.last_week', 'Previous week')
            ->where('durationOptions.this_month', 'This month')
            ->where('durationOptions.last_month', 'Last month'));
});

it('filters marketer donations by device type', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'ashvini',
        'email' => 'ashvini-device-filter@example.com',
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-device-mobile',
        'donor_name' => 'Mobile Donor',
        'donor_email' => 'mobile@example.com',
        'donor_phone' => '9876500101',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'ashvini',
        'device_type' => 'mobile',
        'utm_campaign' => 'Feed',
        'ip_address' => '203.0.113.10',
        'ip_city' => 'Rajkot',
        'ip_region_name' => 'Gujarat',
        'ip_country_name' => 'India',
        'ip_country_code' => 'IN',
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-device-desktop',
        'donor_name' => 'Desktop Donor',
        'donor_email' => 'desktop@example.com',
        'donor_phone' => '9876500102',
        'currency' => 'INR',
        'total_amount' => 900,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'ashvini',
        'device_type' => 'desktop',
        'utm_campaign' => 'Feed',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.donations', [
            'duration' => 'all',
            'device_type' => 'mobile',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Donations')
            ->has('donations.data', 1)
            ->where('donations.data.0.device', 'mobile')
            ->where('donations.data.0.amount', 500)
            ->where('donations.data.0.ip_address', '203.0.113.10')
            ->where('donations.data.0.ip_location', 'Rajkot, Gujarat, India')
            ->where('filters.device_type', 'mobile')
            ->where('summary.donations', 1));
});

it('exports marketer donations as csv without donor identity', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'ashvini',
        'email' => 'ashvini-export-csv@example.com',
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-export-csv',
        'donor_name' => 'Secret Donor',
        'donor_email' => 'secret@example.com',
        'donor_phone' => '9876500199',
        'currency' => 'INR',
        'total_amount' => 1250,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'ashvini',
        'device_type' => 'mobile',
        'utm_campaign' => 'Export Campaign',
        'city' => 'Ahmedabad',
    ]);

    $response = actingAs($ashvini)
        ->get(route('marketer.donations.export', [
            'duration' => 'all',
            'format' => 'csv',
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();
    expect($csv)
        ->toContain('Export Campaign')
        ->toContain('Ahmedabad')
        ->toContain('1250')
        ->not->toContain('Secret Donor')
        ->not->toContain('secret@example.com')
        ->not->toContain('9876500199');
});

it('sorts marketer donations by amount', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'ashvini',
        'email' => 'ashvini-sort-amount@example.com',
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-sort-low',
        'donor_name' => 'Low Donor',
        'donor_email' => 'low@example.com',
        'donor_phone' => '9876500201',
        'currency' => 'INR',
        'total_amount' => 200,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subHour(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'ashvini',
        'device_type' => 'mobile',
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'marketer-sort-high',
        'donor_name' => 'High Donor',
        'donor_email' => 'high@example.com',
        'donor_phone' => '9876500202',
        'currency' => 'INR',
        'total_amount' => 800,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'ashvini',
        'device_type' => 'desktop',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.donations', [
            'duration' => 'all',
            'sort' => 'amount',
            'dir' => 'asc',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Donations')
            ->where('sort', 'amount')
            ->where('dir', 'asc')
            ->where('donations.data.0.amount', 200)
            ->where('donations.data.1.amount', 800));
});

it('exports marketer visits as excel', function () {
    $ashvini = digitalMarketer([
        'referral_code' => 'ashvini',
        'email' => 'ashvini-export-xlsx@example.com',
    ]);

    marketerVisit($ashvini, [
        'utm_campaign' => 'Excel Visit',
        'device_type' => 'tablet',
    ]);

    $response = actingAs($ashvini)
        ->get(route('marketer.visits.export', [
            'duration' => 'all',
            'format' => 'xlsx',
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/vnd.ms-excel');
    expect($response->getContent())
        ->toContain('Excel Visit')
        ->toContain('Tablet');
});

it('aligns clicks Donated with dashboard Paid donations when click was yesterday and pay is today', function () {
    Carbon::setTestNow('2026-09-18 12:00:00');

    $ashvini = digitalMarketer([
        'referral_code' => 'lgwrin',
        'email' => 'lgwrin-parity@example.com',
        'name' => 'Kashyap',
    ]);

    $visit = marketerVisit($ashvini, [
        'utm_campaign' => 'Parity Campaign',
        'converted' => false,
    ]);
    $visit->created_at = now()->subDay();
    $visit->updated_at = now()->subDay();
    $visit->save();

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'parity-paid-today',
        'donor_name' => 'Parity Donor',
        'donor_email' => 'parity@example.com',
        'donor_phone' => '9876500999',
        'currency' => 'INR',
        'total_amount' => 501,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'lgwrin',
        'utm_campaign' => 'Parity Campaign',
        'device_type' => 'mobile',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.dashboard', ['duration' => 'today']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Dashboard')
            ->where('summary.donations', 1));

    actingAs($ashvini)
        ->get(route('marketer.visits', ['duration' => 'today']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Visits')
            ->where('resultBreakdown', function ($breakdown) {
                $donated = collect($breakdown)->firstWhere('name', 'Donated');

                return $donated && (int) $donated['count'] === 1;
            }));

    Carbon::setTestNow();
});

it('lists result=donated visits by converted_at even when the click was yesterday', function () {
    Carbon::setTestNow('2026-09-18 15:00:00');

    $ashvini = digitalMarketer([
        'referral_code' => 'lgwrin2',
        'email' => 'lgwrin-donated-filter@example.com',
    ]);

    $visit = marketerVisit($ashvini, [
        'utm_campaign' => 'Converted Yesterday Click',
        'converted' => true,
        'converted_amount' => 700,
        'converted_at' => now(),
    ]);
    $visit->created_at = now()->subDay();
    $visit->updated_at = now()->subDay();
    $visit->save();

    actingAs($ashvini)
        ->get(route('marketer.visits', [
            'duration' => 'today',
            'result' => 'donated',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Visits')
            ->has('visits.data', 1)
            ->where('visits.data.0.utm_campaign', 'Converted Yesterday Click')
            ->where('visits.data.0.converted', true));

    Carbon::setTestNow();
});

it('marks an attachable visit converted when paid order has partner_user_id', function () {
    Carbon::setTestNow('2026-09-18 16:00:00');

    $ashvini = digitalMarketer([
        'referral_code' => 'attachsid',
        'email' => 'attach-convert@example.com',
    ]);

    $visit = marketerVisit($ashvini, [
        'converted' => false,
        'donation_order_id' => null,
    ]);
    $visit->created_at = now()->subHours(2);
    $visit->save();

    $order = DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attach-convert-order',
        'donor_name' => 'Attach Donor',
        'donor_email' => 'attach@example.com',
        'donor_phone' => '9876500888',
        'currency' => 'INR',
        'total_amount' => 1200,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $ashvini->id,
        'partner_code' => 'attachsid',
    ]);

    $converted = app(\App\Services\LinkTrackingService::class)->markConverted($order->fresh());

    expect($converted)->not->toBeNull()
        ->and($converted->converted)->toBeTrue()
        ->and((int) $converted->donation_order_id)->toBe($order->id)
        ->and((float) $converted->converted_amount)->toBe(1200.0);

    $visit->refresh();
    expect($visit->converted)->toBeTrue()
        ->and((int) $visit->donation_order_id)->toBe($order->id);

    Carbon::setTestNow();
});
