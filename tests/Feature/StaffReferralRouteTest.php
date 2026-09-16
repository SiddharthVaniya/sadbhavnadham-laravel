<?php

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationCampaign;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\AdminInertiaResources;
use App\Support\StaffReferral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['donation.staff_vanity_urls_enabled' => false]);
});

it('builds utm share urls from a staff referral code', function () {
    expect(StaffReferral::trackedShareUrl('https://donate.example.test/donate/old-age-home', 'paz'))
        ->toBe('https://donate.example.test/donate/old-age-home?sid=paz&utm_source=staff&utm_medium=referral')
        ->and(StaffReferral::trackedShareUrl('https://donate.example.test/donate/old-age-home?package_id=12', 'paz'))
        ->toBe('https://donate.example.test/donate/old-age-home?package_id=12&sid=paz&utm_source=staff&utm_medium=referral')
        ->and(StaffReferral::trackedShareUrl('https://donate.example.test/give/spring-drive', 'paz'))
        ->toBe('https://donate.example.test/give/spring-drive?sid=paz&utm_source=staff&utm_medium=referral')
        ->and(StaffReferral::trackedShareUrl('https://donate.example.test/', 'paz'))
        ->toBe('https://donate.example.test/?sid=paz&utm_source=staff&utm_medium=referral')
        ->and(StaffReferral::trackedShareUrl('https://donate.example.test/donate/old-age-home', null))
        ->toBe('https://donate.example.test/donate/old-age-home');
});

it('builds legacy vanity share urls when vanity mode is enabled', function () {
    config(['donation.staff_vanity_urls_enabled' => true]);

    expect(StaffReferral::trackedShareUrl('https://donate.example.test/donate/old-age-home', 'paz'))
        ->toBe('https://donate.example.test/paz/donate/old-age-home');
});

it('rejects reserved or unknown referral codes on legacy vanity routes', function () {
    $cause = Cause::factory()->create(['slug' => 'old-age-home', 'is_active' => true]);

    $this->get('/admin')->assertRedirect();
    $this->get('/missing-code/donate/'.$cause->slug)->assertNotFound();
});

it('redirects legacy vanity cause urls to canonical utm donate urls', function () {
    User::factory()->withReferralCode('paz')->create();
    $cause = Cause::factory()->create(['slug' => 'old-age-home', 'is_active' => true]);
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'is_active' => true,
    ]);

    $response = $this->get('/paz/donate/old-age-home?package_id='.$package->id);

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('/donate/old-age-home')
        ->toContain('sid=paz')
        ->toContain('utm_source=staff')
        ->toContain('package_id='.$package->id)
        ->not->toContain('pid=');
});

it('redirects legacy vanity campaign urls to canonical utm give urls', function () {
    User::factory()->withReferralCode('riya')->create();
    $cause = Cause::factory()->create(['is_active' => true]);
    $campaign = DonationCampaign::factory()->create([
        'cause_id' => $cause->id,
        'slug' => 'spring-drive',
        'is_active' => true,
        'recurring_only' => false,
        'amount' => 501,
        'title' => 'Spring Drive',
    ]);

    $response = $this->get('/riya/give/spring-drive');

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('/give/spring-drive')
        ->toContain('sid=riya')
        ->and($response->headers->get('Location'))
        ->not->toContain('pid=');
});

it('sets attribution cookie after following a legacy vanity redirect', function () {
    User::factory()->withReferralCode('paz')->create();
    $cause = Cause::factory()->create(['slug' => 'old-age-home', 'is_active' => true]);

    $this->followingRedirects()
        ->get('/paz/donate/old-age-home')
        ->assertOk()
        ->assertCookie('donation_analytics_utm');
});

it('keeps staff utm_content on checkout after navigating between cause pages', function () {
    User::factory()->withReferralCode('mjv')->create();
    Cause::factory()->create(['slug' => 'tree-plantation', 'is_active' => true]);
    $home = Cause::factory()->create(['slug' => 'old-age-home', 'is_active' => true]);

    $this->get('/donate/tree-plantation?utm_source=staff&utm_medium=referral&utm_content=mjv')
        ->assertOk()
        ->assertCookie('donation_analytics_utm');

    $utmCookie = json_encode([
        'utm_source' => 'staff',
        'utm_medium' => 'referral',
        'utm_content' => 'mjv',
    ], JSON_UNESCAPED_UNICODE);

    $this->withUnencryptedCookie('donation_analytics_utm', $utmCookie)
        ->get('/donate/old-age-home')
        ->assertOk();

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'attr-staff-persist-1',
        'donor_name' => 'Staff Persist Donor',
        'donor_email' => 'persist@example.com',
        'donor_phone' => '9876543212',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $home->id,
        'cause' => $home->slug,
        'title' => 'Meal',
        'quantity' => 1,
        'unit_amount' => 500,
        'amount' => 500,
    ]);

    $checkout = Request::create('/donate/razorpay', 'POST', [
        'utm_source' => 'staff',
        'utm_medium' => 'referral',
        'utm_content' => 'mjv',
    ]);
    $checkout->cookies->set('donation_analytics_utm', $utmCookie);

    app(AnalyticsService::class)->trackCheckoutStarted($checkout, $order->fresh());

    expect($order->fresh()->utm_content)->toBe('mjv')
        ->and($order->fresh()->utm_source)->toBe('staff');
});

it('embeds utm staff tracking in admin share urls', function () {
    Permission::firstOrCreate(['name' => 'manage causes']);
    Permission::firstOrCreate(['name' => 'copy package links']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['manage causes', 'copy package links']);

    $staff = User::factory()->withReferralCode('paz')->create();
    $staff->assignRole($role);

    $cause = Cause::factory()->create(['slug' => 'old-age-home', 'is_active' => true]);
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'is_active' => true,
    ]);
    $package->load('cause');

    actingAs($staff);

    $causeRow = AdminInertiaResources::causeListRow($cause);
    $packageRow = AdminInertiaResources::packageListRow($package);

    expect($causeRow['share_url'])->toContain('https://sadbhavnadham.org/donate/old-age-home')
        ->toContain('sid=paz')
        ->toContain('utm_source=staff')
        ->and($causeRow['share_url'])->not->toContain('pid=')
        ->and($packageRow['share_url'])->toContain('https://sadbhavnadham.org/donate/old-age-home')
        ->toContain('sid=paz')
        ->toContain('package_id='.$package->id)
        ->and($packageRow['share_url'])->not->toContain('pid=');
});
