<?php

use App\Models\Cause;
use App\Models\User;
use App\Support\AdminInertiaResources;
use App\Support\AdminStaffReferralsData;
use App\Support\DonationPublicFrontend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'donation.public_frontend_url' => 'https://sadbhavnadham.org',
        'donation.redirect_public_donate_to_frontend' => true,
        'donation.staff_vanity_urls_enabled' => false,
        'app.url' => 'https://donate.sadbhavnadham.org',
    ]);
});

it('redirects public cause pages to the nextjs domain and keeps tracking query params', function () {
    $cause = Cause::factory()->create([
        'slug' => 'tree-plantation',
        'is_active' => true,
    ]);

    $response = $this->get('/donate/tree-plantation?utm_source=meta&sid=ashvini&utm_id=120211&package_id=9');

    $response->assertRedirect();
    expect($response->status())->toBe(301)
        ->and($response->headers->get('Location'))
        ->toBe(DonationPublicFrontend::donateCauseUrl($cause->slug, [
            'utm_source' => 'meta',
            'sid' => 'ashvini',
            'utm_id' => '120211',
            'package_id' => '9',
        ]));
});

it('redirects the laravel donate homepage to the nextjs origin', function () {
    $response = $this->get('/?utm_source=staff&sid=paz');

    $response->assertRedirect();
    expect($response->status())->toBe(301)
        ->and($response->headers->get('Location'))
        ->toStartWith('https://sadbhavnadham.org/')
        ->and($response->headers->get('Location'))
        ->toContain('utm_source=staff')
        ->toContain('sid=paz');
});

it('redirects danamojo widget routes to the nextjs danamojo page', function () {
    $base = $this->get('/donate/danamojo-widget');
    $withCause = $this->get('/donate/danamojo-widget/tree-plantation');
    $legacy = $this->get('/donate/danamojo/old-age-home');

    $base->assertRedirect('https://sadbhavnadham.org/donate/danamojo-widget');
    $withCause->assertRedirect('https://sadbhavnadham.org/donate/danamojo-widget/tree-plantation');
    $legacy->assertRedirect('https://sadbhavnadham.org/donate/danamojo-widget/old-age-home');
});

it('does not redirect thank-you or checkout endpoints', function () {
    expect($this->get('/donate/thank-you/not-a-real-order')->status())->not->toBe(301);

    $checkout = $this->post('/donate/razorpay', []);

    expect($checkout->status())->not->toBe(301)
        ->and((string) $checkout->headers->get('Location'))->not->toStartWith('https://sadbhavnadham.org');
});

it('sends legacy vanity cause links to the nextjs donate url in one hop', function () {
    User::factory()->withReferralCode('paz')->create();
    Cause::factory()->create(['slug' => 'old-age-home', 'is_active' => true]);

    $response = $this->get('/paz/donate/old-age-home?package_id=12');

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toStartWith('https://sadbhavnadham.org/donate/old-age-home')
        ->toContain('sid=paz')
        ->toContain('package_id=12')
        ->and($response->headers->get('Location'))
        ->not->toContain('pid=');
});

it('still sends vanity paths to nextjs when vanity mode is on', function () {
    config(['donation.staff_vanity_urls_enabled' => true]);
    User::factory()->withReferralCode('paz')->create();
    Cause::factory()->create(['slug' => 'tree-plantation', 'is_active' => true]);

    $response = $this->get('/paz/donate/tree-plantation');

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toStartWith('https://sadbhavnadham.org/donate/tree-plantation')
        ->toContain('sid=paz')
        ->and($response->headers->get('Location'))
        ->not->toContain('pid=');
});

it('keeps campaign vanity redirects on the laravel donate host', function () {
    User::factory()->withReferralCode('riya')->create();
    $cause = Cause::factory()->create(['is_active' => true]);
    \App\Models\DonationCampaign::factory()->create([
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
        ->not->toStartWith('https://sadbhavnadham.org');
});

it('builds admin cause share and meta urls on the nextjs origin', function () {
    Permission::firstOrCreate(['name' => 'manage causes']);
    Permission::firstOrCreate(['name' => 'view staff referrals']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['manage causes', 'view staff referrals']);

    $staff = User::factory()->withReferralCode('ashvini')->create();
    $staff->assignRole($role);
    $cause = Cause::factory()->create(['slug' => 'dog-shelter', 'is_active' => true]);

    actingAs($staff);

    $shareUrl = AdminInertiaResources::causeListRow($cause)['share_url'];
    $page = AdminStaffReferralsData::pageFromRequest($staff, Request::create('/admin/staff-referrals', 'GET'));

    expect($shareUrl)->toStartWith('https://sadbhavnadham.org/donate/dog-shelter')
        ->and($shareUrl)->toContain('sid=ashvini')
        ->and($shareUrl)->not->toContain('pid=')
        ->and($page['own_share_url'])->toStartWith('https://sadbhavnadham.org/')
        ->and($page['own_share_url'])->toContain('sid=ashvini')
        ->and($page['own_share_url'])->not->toContain('pid=')
        ->and($page['meta_ad_url'])->toStartWith('https://sadbhavnadham.org/?utm_source=meta')
        ->and($page['meta_ad_url'])->toContain('sid=ashvini')
        ->and($page['meta_ad_url'])->not->toContain('pid=');
});
