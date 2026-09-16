<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createReportsAdmin(array $permissions = ['view reports']): User
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

function createPaidDonationForReports(Cause $cause, float $amount, ?\Illuminate\Support\Carbon $paidAt = null): DonationOrder
{
    $paidAt ??= now();

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order-'.uniqid(),
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => $amount,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => $paidAt,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->title,
        'title' => $cause->title,
        'quantity' => 1,
        'unit_amount' => $amount,
        'amount' => $amount,
    ]);

    return $order;
}

it('renders reports page for authorized admins', function () {
    $user = createReportsAdmin();

    actingAs($user)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->has('reportTypes')
            ->has('report'));
});

it('blocks reports page without permission', function () {
    $user = createReportsAdmin(['view donations']);

    actingAs($user)->get(route('admin.reports.index'))->assertForbidden();
});

it('exports monthly by cause report as csv', function () {
    $user = createReportsAdmin();

    $causeA = Cause::factory()->create(['title' => 'Old Age Home']);
    $causeB = Cause::factory()->create(['title' => 'Tree Plantation']);

    createPaidDonationForReports($causeA, 1000, now()->startOfMonth());
    createPaidDonationForReports($causeB, 2500, now());

    $response = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'monthly_by_cause',
        'duration' => 'this_month',
        'format' => 'csv',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)->toContain('Old Age Home');
    expect($content)->toContain('Tree Plantation');
    expect($content)->toContain('1000');
    expect($content)->toContain('2500');
});

it('exports cause summary report as excel', function () {
    $user = createReportsAdmin();

    $cause = Cause::factory()->create(['title' => 'Medical Support']);
    createPaidDonationForReports($cause, 5000);

    $response = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'cause_summary',
        'duration' => 'this_month',
        'format' => 'xlsx',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

    expect($response->getContent())->toContain('Medical Support');
});

it('exports monthly summary report as pdf', function () {
    $user = createReportsAdmin();

    $cause = Cause::factory()->create();
    createPaidDonationForReports($cause, 1500);

    $response = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'monthly_summary',
        'duration' => 'this_month',
        'format' => 'pdf',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('renders day-wise summary for today filter', function () {
    $user = createReportsAdmin();
    $cause = Cause::factory()->create();

    createPaidDonationForReports($cause, 500, now());
    createPaidDonationForReports($cause, 250, now()->subDay());

    actingAs($user)
        ->get(route('admin.reports.index', [
            'type' => 'daily_summary',
            'duration' => 'today',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('duration', 'today')
            ->where('report.type', 'daily_summary')
            ->where('report.rows.0.donation_count', 1)
            ->where('report.grandTotal', 500));
});

it('embeds gujarati-capable font in report pdf exports', function () {
    $user = createReportsAdmin();

    $cause = Cause::factory()->create(['title' => 'વૃદ્ધાશ્રમ']);
    createPaidDonationForReports($cause, 1500, now());

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order-'.uniqid(),
        'donor_name' => 'મુકેશભાઈ પટેલ',
        'donor_email' => 'gujarati@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->title,
        'title' => 'સાદું ભોજન',
        'quantity' => 1,
        'unit_amount' => 500,
        'amount' => 500,
    ]);

    $response = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'donation_detail',
        'duration' => 'this_month',
        'format' => 'pdf',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $pdf = $response->getContent();

    expect($pdf)->toStartWith('%PDF');
    expect($pdf)->toContain('NotoSansGujarati');
});

function createPaidQrDonation(float $amount, ?\Illuminate\Support\Carbon $paidAt = null): DonationOrder
{
    return DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'provider_order_id' => 'qr-'.uniqid(),
        'provider_payment_id' => 'pay_qr_'.uniqid(),
        'donor_name' => 'Unknown Donor',
        'donor_email' => '',
        'donor_phone' => 'upi-'.uniqid(),
        'currency' => 'INR',
        'total_amount' => $amount,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => $paidAt ?? now(),
    ]);
}

it('includes qr donations without a cause in the monthly summary totals', function () {
    $user = createReportsAdmin();

    $cause = Cause::factory()->create(['title' => 'Old Age Home']);
    createPaidDonationForReports($cause, 1000);
    createPaidQrDonation(500);

    $response = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'monthly_summary',
        'duration' => 'this_month',
        'format' => 'csv',
    ]));

    $content = $response->streamedContent();

    expect($content)->toContain('1500');
});

it('shows qr donations as uncategorized in the cause summary', function () {
    $user = createReportsAdmin();

    $cause = Cause::factory()->create(['title' => 'Old Age Home']);
    createPaidDonationForReports($cause, 1000);
    createPaidQrDonation(700);

    $response = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'cause_summary',
        'duration' => 'this_month',
        'format' => 'csv',
    ]));

    $content = $response->streamedContent();

    expect($content)->toContain('Old Age Home');
    expect($content)->toContain('Uncategorized');
    expect($content)->toContain('700');
});

it('includes uncategorized column in monthly by cause report when qr donations exist', function () {
    $user = createReportsAdmin();

    $cause = Cause::factory()->create(['title' => 'Old Age Home']);
    createPaidDonationForReports($cause, 1000);
    createPaidQrDonation(300);

    $response = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'monthly_by_cause',
        'duration' => 'this_month',
        'format' => 'csv',
    ]));

    $content = $response->streamedContent();

    expect($content)->toContain('Uncategorized');
    expect($content)->toContain('1300');
});

it('exports package summary report as csv', function () {
    $user = createReportsAdmin();

    $cause = Cause::factory()->create(['title' => 'Old Age Home']);
    $package = \App\Models\CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'Meals for 20 Elders',
        'amount' => 2000,
    ]);

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order-'.uniqid(),
        'donor_name' => 'Package Donor',
        'donor_email' => 'package@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 2000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause_package_id' => $package->id,
        'cause' => $cause->title,
        'title' => $package->title,
        'quantity' => 1,
        'unit_amount' => 2000,
        'amount' => 2000,
    ]);

    createPaidDonationForReports($cause, 500);

    $response = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'package_summary',
        'duration' => 'this_month',
        'format' => 'csv',
    ]));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toContain('Meals for 20 Elders');
    expect($content)->toContain('Old Age Home');
    expect($content)->toContain('Custom amount / no package');
    expect($content)->toContain('2000');
    expect($content)->toContain('500');
});

it('includes qr donations in package summary so totals match other reports', function () {
    $user = createReportsAdmin();

    $cause = Cause::factory()->create(['title' => 'Old Age Home']);
    $package = \App\Models\CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'Simple Meal',
        'amount' => 1000,
    ]);

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order-'.uniqid(),
        'donor_name' => 'Package Donor',
        'donor_email' => 'package@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause_package_id' => $package->id,
        'cause' => $cause->title,
        'title' => $package->title,
        'quantity' => 1,
        'unit_amount' => 1000,
        'amount' => 1000,
    ]);

    createPaidQrDonation(700);

    $packageResponse = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'package_summary',
        'duration' => 'this_month',
        'format' => 'csv',
    ]));

    $monthlyResponse = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'monthly_summary',
        'duration' => 'this_month',
        'format' => 'csv',
    ]));

    $packageContent = $packageResponse->streamedContent();
    $monthlyContent = $monthlyResponse->streamedContent();

    expect($packageContent)->toContain('Uncategorized');
    expect($packageContent)->toContain('700');
    expect($packageContent)->toContain('1700');
    expect($monthlyContent)->toContain('1700');
});

it('renders package summary on reports page', function () {
    $user = createReportsAdmin();

    actingAs($user)
        ->get(route('admin.reports.index', ['type' => 'package_summary']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('report.type', 'package_summary')
            ->where('reportTypes.package_summary', 'Package-wise donation summary')
            ->where('reportTypes.cause_summary', 'Cause-wise donation summary'));
});

it('filters reports by cause', function () {
    $user = createReportsAdmin();

    $included = Cause::factory()->create(['title' => 'Included Cause']);
    $excluded = Cause::factory()->create(['title' => 'Excluded Cause']);

    createPaidDonationForReports($included, 1000);
    createPaidDonationForReports($excluded, 1000);

    $response = actingAs($user)->get(route('admin.reports.export', [
        'type' => 'cause_summary',
        'duration' => 'this_month',
        'format' => 'csv',
        'cause_id' => $included->id,
    ]));

    $content = $response->streamedContent();

    expect($content)->toContain('Included Cause');
    expect($content)->not->toContain('Excluded Cause');
});

it('exposes state and partner filter options on reports page', function () {
    $user = createReportsAdmin();
    $cause = Cause::factory()->create();
    $partner = User::factory()->withReferralCode('sidabc')->create(['name' => 'Ashvini Partner']);

    $order = createPaidDonationForReports($cause, 800);
    $order->update([
        'state' => 'Gujarat',
        'partner_user_id' => $partner->id,
        'partner_code' => 'sidabc',
    ]);

    actingAs($user)
        ->get(route('admin.reports.index', [
            'type' => 'donation_detail',
            'duration' => 'this_month',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('durationOptions.today', 'Today (daywise)')
            ->where('durationOptions.custom', 'Custom date range')
            ->where('filterOptions.states.0', 'Gujarat')
            ->where('filterOptions.partners.0.name', 'Ashvini Partner')
            ->where('filterOptions.partners.0.code', 'sidabc')
            ->where('reportTypes.daily_summary', 'Day-wise donation summary'));
});

it('filters reports by state', function () {
    $user = createReportsAdmin();
    $cause = Cause::factory()->create();

    $gujarat = createPaidDonationForReports($cause, 500);
    $gujarat->update(['state' => 'Gujarat']);

    $maharashtra = createPaidDonationForReports($cause, 900);
    $maharashtra->update(['state' => 'Maharashtra']);

    actingAs($user)
        ->get(route('admin.reports.index', [
            'type' => 'cause_summary',
            'duration' => 'this_month',
            'state' => 'Gujarat',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('filters.state', 'Gujarat')
            ->where('report.grandTotal', 500));
});

it('filters reports by partner sid code', function () {
    $user = createReportsAdmin();
    $cause = Cause::factory()->create();
    $partner = User::factory()->withReferralCode('zecol')->create(['name' => 'Ayush Raikundaliya']);

    $matched = createPaidDonationForReports($cause, 1200);
    $matched->update([
        'partner_user_id' => $partner->id,
        'partner_code' => 'zecol',
        'state' => 'Rajasthan',
    ]);

    createPaidDonationForReports($cause, 400);

    actingAs($user)
        ->get(route('admin.reports.index', [
            'type' => 'donation_detail',
            'duration' => 'this_month',
            'partner_user_id' => $partner->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('filters.partner_user_id', $partner->id)
            ->where('report.grandTotal', 1200)
            ->where('report.rows.0.partner_name', 'Ayush Raikundaliya')
            ->where('report.rows.0.sid_code', 'zecol')
            ->where('report.rows.0.state', 'Rajasthan'));
});

it('filters reports by source and exposes source options', function () {
    $user = createReportsAdmin();
    $cause = Cause::factory()->create();

    $meta = createPaidDonationForReports($cause, 700);
    $meta->update([
        'attr_source' => 'meta',
        'utm_source' => 'facebook',
    ]);

    $organic = createPaidDonationForReports($cause, 300);
    $organic->update([
        'attr_source' => 'organic',
        'utm_source' => null,
        'utm_medium' => null,
        'referrer' => null,
        'landing_path' => null,
    ]);

    actingAs($user)
        ->get(route('admin.reports.index', [
            'type' => 'cause_summary',
            'duration' => 'this_month',
            'source' => 'meta',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('filters.source', 'meta')
            ->where('filterOptions.sources.0.value', 'meta')
            ->where('filterOptions.sources.0.label', 'Meta')
            ->where('report.grandTotal', 700));
});

it('filters reports by sid direct organic option', function () {
    $user = createReportsAdmin();
    $cause = Cause::factory()->create();
    $partner = User::factory()->withReferralCode('xvjsng')->create();

    $staff = createPaidDonationForReports($cause, 900);
    $staff->update([
        'partner_user_id' => $partner->id,
        'partner_code' => 'xvjsng',
        'attr_source' => 'staff',
        'utm_source' => 'staff',
    ]);

    $organic = createPaidDonationForReports($cause, 450);
    $organic->update([
        'attr_source' => 'organic',
        'utm_source' => null,
        'utm_medium' => null,
        'referrer' => null,
        'landing_path' => null,
    ]);

    actingAs($user)
        ->get(route('admin.reports.index', [
            'type' => 'cause_summary',
            'duration' => 'this_month',
            'partner_user_id' => 'organic',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('filters.partner_user_id', 'organic')
            ->where('report.grandTotal', 450));
});

it('filters reports by all partner donation option', function () {
    $user = createReportsAdmin();
    $cause = Cause::factory()->create();
    $partner = User::factory()->withReferralCode('yfqqld')->create();

    $staff = createPaidDonationForReports($cause, 800);
    $staff->update([
        'partner_user_id' => $partner->id,
        'partner_code' => 'yfqqld',
        'attr_source' => 'staff',
        'utm_source' => 'staff',
    ]);

    $organic = createPaidDonationForReports($cause, 200);
    $organic->update([
        'attr_source' => 'organic',
        'utm_source' => null,
        'utm_medium' => null,
        'referrer' => null,
        'landing_path' => null,
        'utm_content' => null,
        'partner_user_id' => null,
        'partner_code' => null,
    ]);

    actingAs($user)
        ->get(route('admin.reports.index', [
            'type' => 'cause_summary',
            'duration' => 'this_month',
            'partner_user_id' => 'all_partners',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('filters.partner_user_id', 'all_partners')
            ->where('report.grandTotal', 800));
});

it('filters reports by match and mismatch cause', function () {
    $user = createReportsAdmin();

    $oldAge = Cause::factory()->create(['title' => 'Old Age Home', 'slug' => 'old-age-home']);
    $trees = Cause::factory()->create(['title' => 'Tree Plantation', 'slug' => 'tree-plantation']);

    $matched = createPaidDonationForReports($oldAge, 1000);
    $matched->update(['landing_path' => '/donate/old-age-home?utm_source=meta']);

    $mismatched = createPaidDonationForReports($trees, 750);
    $mismatched->update(['landing_path' => '/causes/old-age-home?sid=ashvini']);

    $unknown = createPaidDonationForReports($trees, 300);
    $unknown->update(['landing_path' => '/']);

    actingAs($user)
        ->get(route('admin.reports.index', [
            'type' => 'cause_summary',
            'duration' => 'this_month',
            'cause_match' => 'match',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('filters.cause_match', 'match')
            ->where('causeMatchOptions.match', 'Match cause')
            ->where('report.grandTotal', 1000));

    actingAs($user)
        ->get(route('admin.reports.index', [
            'type' => 'cause_summary',
            'duration' => 'this_month',
            'cause_match' => 'mismatch',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('filters.cause_match', 'mismatch')
            ->where('report.grandTotal', 750));
});
