<?php

use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function createDonationsAdminUser(array $permissions = ['view donations', 'view all donations']): User
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

it('shows latest donations first on the admin donations index', function () {
    $user = createDonationsAdminUser();

    $olderDonation = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'older-order',
        'donor_name' => 'Older Donor',
        'donor_email' => 'older@example.com',
        'donor_phone' => '9999999998',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
    ]);
    $olderDonation->forceFill(['created_at' => now()->subDay()])->save();

    $latestDonation = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'latest-order',
        'donor_name' => 'Latest Donor',
        'donor_email' => 'latest@example.com',
        'donor_phone' => '9999999997',
        'currency' => 'INR',
        'total_amount' => 700,
        'status' => DonationOrder::STATUS_PAID,
    ]);
    $latestDonation->forceFill(['created_at' => now()])->save();

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 2)
            ->where('donations.data.0.id', $latestDonation->id)
            ->where('donations.data.0.donor_name', 'Latest Donor')
            ->where('donations.data.1.id', $olderDonation->id));
});

it('orders donations by latest first on the inertia index (legacy filter endpoint removed)', function () {
    $user = createDonationsAdminUser();

    $olderDonation = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'filtered-older-order',
        'donor_name' => 'Filtered Older Donor',
        'donor_email' => 'filtered-older@example.com',
        'donor_phone' => '9999999996',
        'currency' => 'INR',
        'total_amount' => 400,
        'status' => DonationOrder::STATUS_PAID,
    ]);
    $olderDonation->forceFill(['created_at' => now()->subHours(3)])->save();

    $latestDonation = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'filtered-latest-order',
        'donor_name' => 'Filtered Latest Donor',
        'donor_email' => 'filtered-latest@example.com',
        'donor_phone' => '9999999995',
        'currency' => 'INR',
        'total_amount' => 900,
        'status' => DonationOrder::STATUS_PAID,
    ]);
    $latestDonation->forceFill(['created_at' => now()])->save();

    expect(fn () => route('admin.donations.filter'))->toThrow(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);

    actingAs($user)
        ->get(route('admin.donations.index', [
            'duration' => 'all',
            'status' => DonationOrder::STATUS_PAID,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->where('donations.data.0.id', $latestDonation->id)
            ->where('donations.data.0.donor_name', 'Filtered Latest Donor'));
});

it('exports filtered donations as csv', function () {
    $user = createDonationsAdminUser();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'export-order-1',
        'provider_payment_id' => 'pay_export_1',
        'donor_name' => 'Export Donor',
        'donor_email' => 'export@example.com',
        'donor_phone' => '9999999901',
        'currency' => 'INR',
        'total_amount' => 1200,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    DonationOrder::create([
        'payment_provider' => 'offline',
        'provider_order_id' => 'export-order-2',
        'donor_name' => 'Other Donor',
        'donor_email' => 'other@example.com',
        'donor_phone' => '9999999902',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    actingAs($user);
    $response = get(route('admin.donations.export', [
        'duration' => 'all',
        'status' => DonationOrder::STATUS_PAID,
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toContain('Export Donor')
        ->and($csv)->not->toContain('Other Donor')
        ->and($csv)->not->toContain('Source')
        ->and($csv)->not->toContain('UTM Campaign')
        ->and($csv)->not->toContain('UTM Content')
        ->and($csv)->not->toContain('Receipt Status');
});

it('shows overview widgets and payment method split on donations index', function () {
    $user = createDonationsAdminUser();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'overview-order-1',
        'donor_name' => 'Overview Paid Donor',
        'donor_email' => 'overview-paid@example.com',
        'donor_phone' => '9999999994',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay_qr',
        'provider_order_id' => 'overview-order-paid-qr',
        'donor_name' => 'Overview Paid QR Donor',
        'donor_email' => 'overview-paid-qr@example.com',
        'donor_phone' => '9999999992',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    DonationOrder::create([
        'payment_provider' => 'offline',
        'provider_order_id' => 'overview-order-2',
        'donor_name' => 'Overview Failed Donor',
        'donor_email' => 'overview-failed@example.com',
        'donor_phone' => '9999999993',
        'currency' => 'INR',
        'total_amount' => 700,
        'status' => DonationOrder::STATUS_FAILED,
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->where('paidAmount', 2000)
            ->where('attemptVolume', 2700)
            ->where('failedCount', 1)
            ->where('totalCount', 3)
            ->where('statusCounts.paid', 2)
            ->where('statusCounts.failed', 1)
            ->has('paymentMethodBreakdown', 2)
            ->where('paymentMethodBreakdown.0.name', 'Razorpay')
            ->where('paymentMethodBreakdown.0.amount', 1500)
            ->where('paymentMethodBreakdown.0.percentage', 75)
            ->where('paymentMethodBreakdown.1.name', 'Razorpay QR')
            ->where('paymentMethodBreakdown.1.amount', 500)
            ->where('paymentMethodBreakdown.1.percentage', 25)
            ->where('paymentMethodBreakdown', function ($breakdown) {
                $names = collect($breakdown)->pluck('name')->all();

                return ! in_array('Offline', $names, true);
            }));
});

it('includes paid donations in today view when payment completed today even if checkout started earlier', function () {
    $user = createDonationsAdminUser();

    $backloggedDonation = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'backlogged-order',
        'provider_payment_id' => 'pay_backlogged',
        'donor_name' => 'Kamal Mirchandani',
        'donor_email' => 'kamal@example.com',
        'donor_phone' => '9833013371',
        'currency' => 'INR',
        'total_amount' => 2100,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => '546',
        'paid_at' => now(),
    ]);
    $backloggedDonation->forceFill(['created_at' => now()->subDays(2)])->save();

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'today']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 1)
            ->where('donations.data.0.donor_name', 'Kamal Mirchandani')
            ->where('paidAmount', 2100));
});

it('includes city on donations index rows instead of receipt status', function () {
    $user = createDonationsAdminUser();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'city-order',
        'donor_name' => 'City Donor',
        'donor_email' => 'city@example.com',
        'donor_phone' => '9999999900',
        'city' => 'Mumbai',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->where('donations.data.0.city', 'Mumbai')
            ->missing('donations.data.0.receipt_label'));
});

it('includes razorpay qr donations when filtering by razorpay provider', function () {
    $user = createDonationsAdminUser();

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'provider-filter-rzp',
        'donor_name' => 'Checkout Donor',
        'donor_email' => 'checkout@example.com',
        'donor_phone' => '9999999801',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'provider_order_id' => 'qr-provider-filter',
        'provider_payment_id' => 'pay_provider_filter_qr',
        'donor_name' => 'QR Donor',
        'donor_email' => '',
        'donor_phone' => 'upi-abc123',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_OFFLINE,
        'provider_order_id' => 'manual-provider-filter',
        'donor_name' => 'Offline Donor',
        'donor_email' => '',
        'donor_phone' => '9999999802',
        'currency' => 'INR',
        'total_amount' => 300,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all', 'provider' => 'razorpay']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 2)
            ->where('paidAmount', 1500));

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all', 'provider' => 'razorpay_qr']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('donations.data', 1)
            ->where('donations.data.0.donor_name', 'QR Donor')
            ->where('donations.data.0.provider', 'Razorpay QR'));

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all', 'provider' => 'offline']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('donations.data', 1)
            ->where('donations.data.0.donor_name', 'Offline Donor'));
});

it('filters donations by package on the admin donations index', function () {
    $user = createDonationsAdminUser();

    $cause = \App\Models\Cause::factory()->create(['title' => 'Dog Shelter']);
    $targetPackage = \App\Models\CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'Monthly Care',
    ]);
    $otherPackage = \App\Models\CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'One-time Feed',
    ]);

    $matchingDonation = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'package-match-order',
        'donor_name' => 'Package Match Donor',
        'donor_email' => 'match@example.com',
        'donor_phone' => '9999999899',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    \App\Models\DonationItem::create([
        'donation_order_id' => $matchingDonation->id,
        'cause_id' => $cause->id,
        'cause_package_id' => $targetPackage->id,
        'cause' => $cause->title,
        'title' => $targetPackage->title,
        'quantity' => 1,
        'unit_amount' => 1500,
        'amount' => 1500,
    ]);

    $otherDonation = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'package-other-order',
        'donor_name' => 'Other Package Donor',
        'donor_email' => 'other-package@example.com',
        'donor_phone' => '9999999898',
        'currency' => 'INR',
        'total_amount' => 900,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    \App\Models\DonationItem::create([
        'donation_order_id' => $otherDonation->id,
        'cause_id' => $cause->id,
        'cause_package_id' => $otherPackage->id,
        'cause' => $cause->title,
        'title' => $otherPackage->title,
        'quantity' => 1,
        'unit_amount' => 900,
        'amount' => 900,
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', [
            'duration' => 'all',
            'package_id' => $targetPackage->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 1)
            ->where('donations.data.0.donor_name', 'Package Match Donor')
            ->where('filters.package_id', (string) $targetPackage->id)
            ->has('packages', 2));
});

it('searches donations across the full database even when duration is today', function () {
    $user = createDonationsAdminUser();

    $olderDonation = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'search-older-order',
        'provider_payment_id' => 'pay_SEARCH_OLD',
        'donor_name' => 'Krunal Bhanderi',
        'donor_email' => 'krunal-old@example.com',
        'donor_phone' => '9876501234',
        'receipt_number' => 4242,
        'currency' => 'INR',
        'total_amount' => 1200,
        'status' => DonationOrder::STATUS_PAID,
    ]);
    $olderDonation->forceFill(['created_at' => now()->subDays(12)])->save();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'search-today-other',
        'provider_payment_id' => 'pay_TODAY_OTHER',
        'donor_name' => 'Someone Else',
        'donor_email' => 'other-today@example.com',
        'donor_phone' => '9876509999',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', [
            'duration' => 'today',
            'search' => 'Krunal',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->where('duration', 'all')
            ->where('overviewDateLabel', 'All time')
            ->has('donations.data', 1)
            ->where('donations.data.0.id', $olderDonation->id)
            ->where('donations.data.0.donor_name', 'Krunal Bhanderi')
            ->where('filters.search', 'Krunal'));
});

it('finds donations by phone payment id and receipt number in search', function (string $search) {
    $user = createDonationsAdminUser();

    $match = DonationOrder::create([
        'payment_provider' => 'offline',
        'provider_order_id' => 'manual-search-match',
        'provider_payment_id' => 'pay_UNIQUE_MATCH',
        'donor_name' => 'Match Donor',
        'donor_email' => 'match-donor@example.com',
        'donor_phone' => '9123456780',
        'receipt_number' => 7788,
        'currency' => 'INR',
        'total_amount' => 800,
        'status' => DonationOrder::STATUS_PAID,
    ]);
    $match->forceFill(['created_at' => now()->subMonth()])->save();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'manual-search-other',
        'provider_payment_id' => 'pay_OTHER',
        'donor_name' => 'Other Donor',
        'donor_email' => 'other-donor@example.com',
        'donor_phone' => '9000000000',
        'receipt_number' => 1111,
        'currency' => 'INR',
        'total_amount' => 300,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', [
            'duration' => 'today',
            'search' => $search,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 1)
            ->where('donations.data.0.id', $match->id));
})->with([
    'phone' => ['9123456780'],
    'payment id' => ['pay_UNIQUE_MATCH'],
    'receipt' => ['7788'],
]);

it('still respects an explicit date range when searching', function () {
    $user = createDonationsAdminUser();

    $insideRange = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'range-inside',
        'donor_name' => 'Range Donor',
        'donor_email' => 'range-inside@example.com',
        'donor_phone' => '9111111111',
        'currency' => 'INR',
        'total_amount' => 400,
        'status' => DonationOrder::STATUS_PAID,
    ]);
    $insideRange->forceFill(['created_at' => now()->subDays(3)])->save();

    $outsideRange = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'range-outside',
        'donor_name' => 'Range Donor',
        'donor_email' => 'range-outside@example.com',
        'donor_phone' => '9222222222',
        'currency' => 'INR',
        'total_amount' => 450,
        'status' => DonationOrder::STATUS_PAID,
    ]);
    $outsideRange->forceFill(['created_at' => now()->subDays(40)])->save();

    actingAs($user)
        ->get(route('admin.donations.index', [
            'from_date' => now()->subDays(7)->toDateString(),
            'to_date' => now()->toDateString(),
            'search' => 'Range Donor',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 1)
            ->where('donations.data.0.id', $insideRange->id));
});

it('sorts donations by amount across the full result set', function () {
    $user = createDonationsAdminUser();

    $low = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'sort-low',
        'donor_name' => 'Low Amount',
        'donor_email' => 'low@example.com',
        'donor_phone' => '9111111111',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'utm_source' => 'facebook',
    ]);
    $high = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'sort-high',
        'donor_name' => 'High Amount',
        'donor_email' => 'high@example.com',
        'donor_phone' => '9222222222',
        'currency' => 'INR',
        'total_amount' => 9000,
        'status' => DonationOrder::STATUS_PAID,
        'utm_source' => 'instagram',
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', [
            'duration' => 'all',
            'sort' => 'total_amount',
            'dir' => 'desc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->where('sort', 'total_amount')
            ->where('dir', 'desc')
            ->where('donations.data.0.id', $high->id)
            ->where('donations.data.1.id', $low->id));
});

it('filters donations by marketing source and campaign', function () {
    $user = createDonationsAdminUser();

    $facebook = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'src-fb',
        'donor_name' => 'FB Donor',
        'donor_email' => 'fb@example.com',
        'donor_phone' => '9333333333',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'utm_source' => 'facebook',
        'utm_campaign' => 'summer_feed',
        'utm_content' => 'pritesh',
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'src-wa',
        'donor_name' => 'WA Donor',
        'donor_email' => 'wa@example.com',
        'donor_phone' => '9444444444',
        'currency' => 'INR',
        'total_amount' => 600,
        'status' => DonationOrder::STATUS_PAID,
        'utm_source' => 'whatsapp',
        'utm_campaign' => 'wa_blast',
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', [
            'duration' => 'all',
            'source' => 'facebook',
            'utm_campaign' => 'summer_feed',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 1)
            ->where('donations.data.0.id', $facebook->id)
            ->where('donations.data.0.source', 'Meta · Facebook · Organic social')
            ->where('filters.source', 'facebook')
            ->where('filters.utm_campaign', 'summer_feed'));
});

it('filters donations by min and max amount', function () {
    $user = createDonationsAdminUser();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'amt-low',
        'donor_name' => 'Low Donor',
        'donor_email' => 'low-amt@example.com',
        'donor_phone' => '9555555551',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    $inRange = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'amt-mid',
        'donor_name' => 'Mid Donor',
        'donor_email' => 'mid-amt@example.com',
        'donor_phone' => '9555555552',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'amt-high',
        'donor_name' => 'High Donor',
        'donor_email' => 'high-amt@example.com',
        'donor_phone' => '9555555553',
        'currency' => 'INR',
        'total_amount' => 2000,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', [
            'duration' => 'all',
            'min_amount' => 400,
            'max_amount' => 1000,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 1)
            ->where('donations.data.0.id', $inRange->id)
            ->where('filters.min_amount', '400')
            ->where('filters.max_amount', '1000'));
});

it('exports csv using status and amount filters', function () {
    $user = createDonationsAdminUser();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'csv-paid-in-range',
        'provider_payment_id' => 'pay_csv_paid',
        'donor_name' => 'Paid In Range',
        'donor_email' => 'paid-in-range@example.com',
        'donor_phone' => '9555555561',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'csv-failed-in-range',
        'provider_payment_id' => 'pay_csv_failed',
        'donor_name' => 'Failed In Range',
        'donor_email' => 'failed-in-range@example.com',
        'donor_phone' => '9555555562',
        'currency' => 'INR',
        'total_amount' => 750,
        'status' => DonationOrder::STATUS_FAILED,
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'csv-failed-out-of-range',
        'provider_payment_id' => 'pay_csv_failed_high',
        'donor_name' => 'Failed Out Of Range',
        'donor_email' => 'failed-out@example.com',
        'donor_phone' => '9555555563',
        'currency' => 'INR',
        'total_amount' => 5000,
        'status' => DonationOrder::STATUS_FAILED,
    ]);

    actingAs($user);
    $response = get(route('admin.donations.export', [
        'duration' => 'all',
        'status' => DonationOrder::STATUS_FAILED,
        'min_amount' => 400,
        'max_amount' => 1000,
    ]));

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Failed In Range')
        ->and($csv)->not->toContain('Paid In Range')
        ->and($csv)->not->toContain('Failed Out Of Range');
});
