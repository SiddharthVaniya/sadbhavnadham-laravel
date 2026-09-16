<?php

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\User;
use App\Services\DonationAttributionService;
use App\Services\RazorpaySubscriptionService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createSubscriptionsAdminUser(): User
{
    Permission::firstOrCreate(['name' => 'view subscriptions', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view subscriptions');

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lists subscriptions for authorized admins', function () {
    $user = createSubscriptionsAdminUser();

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Meera Shah',
        'donor_email' => 'meera@example.com',
    ]);

    actingAs($user)
        ->get(route('admin.subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Subscriptions/Index')
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.donor_name', 'Meera Shah'));
});

it('filters subscriptions by search term', function () {
    $user = createSubscriptionsAdminUser();

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Meera Shah',
        'donor_email' => 'meera@example.com',
    ]);

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Ravi Patel',
        'donor_email' => 'ravi@example.com',
    ]);

    actingAs($user)
        ->get(route('admin.subscriptions.index', ['search' => 'meera@example.com']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.donor_email', 'meera@example.com'));
});

it('filters subscriptions by package, cause title, source, platform, and employee', function () {
    $user = createSubscriptionsAdminUser();

    $cause = Cause::factory()->create([
        'title' => 'Old Age Home',
        'allow_recurring' => true,
    ]);
    $breakfast = CausePackage::factory()->recurring()->create([
        'cause_id' => $cause->id,
        'title' => 'Morning Breakfast',
    ]);
    $lunch = CausePackage::factory()->recurring()->create([
        'cause_id' => $cause->id,
        'title' => 'Afternoon Lunch',
    ]);

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Package Match',
        'donor_email' => 'match-sub@example.com',
        'cause_id' => $cause->id,
        'cause_package_id' => $breakfast->id,
        'item_title' => $breakfast->title,
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'Ashvini | 20/08 | Sadbhavna | Bday',
        'utm_content' => 'Ashvini',
        'attr_source' => 'meta',
        'attr_medium' => 'paid_social',
        'attr_platform' => 'instagram',
    ]);

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Other Package',
        'donor_email' => 'other-sub@example.com',
        'cause_id' => $cause->id,
        'cause_package_id' => $lunch->id,
        'item_title' => $lunch->title,
        'utm_source' => 'google',
        'utm_campaign' => 'search_brand',
        'utm_content' => 'Kiran',
        'attr_source' => 'google',
        'attr_platform' => 'facebook',
    ]);

    actingAs($user)
        ->get(route('admin.subscriptions.index', [
            'status' => 'all',
            'package_id' => $breakfast->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Subscriptions/Index')
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.donor_name', 'Package Match')
            ->where('filters.package_id', (string) $breakfast->id)
            ->has('packages')
            ->has('sourceOptions')
            ->has('platformOptions')
            ->has('campaignOptions')
            ->has('employeeOptions'));

    actingAs($user)
        ->get(route('admin.subscriptions.index', [
            'status' => 'all',
            'cause_title' => $breakfast->title,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.donor_name', 'Package Match')
            ->where('filters.cause_title', $breakfast->title));

    actingAs($user)
        ->get(route('admin.subscriptions.index', [
            'status' => 'all',
            'source' => 'meta',
            'utm_campaign' => 'Ashvini | 20/08 | Sadbhavna | Bday',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.donor_name', 'Package Match')
            ->where('subscriptions.data.0.source', 'Meta · Instagram · Paid social')
            ->where('filters.source', 'meta')
            ->where('filters.utm_campaign', 'Ashvini | 20/08 | Sadbhavna | Bday'));

    actingAs($user)
        ->get(route('admin.subscriptions.index', [
            'status' => 'all',
            'platform' => 'instagram',
            'utm_content' => 'Ashvini',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.donor_name', 'Package Match')
            ->where('filters.platform', 'instagram')
            ->where('filters.utm_content', 'Ashvini'));
});

it('searches subscriptions by utm campaign and exports matching rows', function () {
    $user = createSubscriptionsAdminUser();

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Campaign Match',
        'donor_email' => 'campaign-match@example.com',
        'utm_campaign' => 'unique_sub_campaign_xyz',
    ]);

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Other Campaign',
        'donor_email' => 'other-campaign@example.com',
        'utm_campaign' => 'other_campaign',
    ]);

    actingAs($user)
        ->get(route('admin.subscriptions.index', [
            'status' => 'all',
            'search' => 'unique_sub_campaign_xyz',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.donor_name', 'Campaign Match'));

    $response = actingAs($user)->get(route('admin.subscriptions.export', [
        'status' => 'all',
        'utm_campaign' => 'unique_sub_campaign_xyz',
    ]));

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('Campaign Match');
    expect($content)->not->toContain('Other Campaign');
});

it('sorts all filtered subscriptions by donor name on the server', function () {
    $user = createSubscriptionsAdminUser();

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Zoya Khan',
        'donor_email' => 'zoya@example.com',
    ]);

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Asha Patel',
        'donor_email' => 'asha@example.com',
    ]);

    actingAs($user)
        ->get(route('admin.subscriptions.index', [
            'status' => 'live',
            'sort' => 'donor_name',
            'dir' => 'asc',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Subscriptions/Index')
            ->where('sort', 'donor_name')
            ->where('dir', 'asc')
            ->where('subscriptions.data.0.donor_name', 'Asha Patel')
            ->where('subscriptions.data.1.donor_name', 'Zoya Khan'));
});

it('shows subscription details and billing history', function () {
    $user = createSubscriptionsAdminUser();

    $subscription = DonationSubscription::factory()->active()->create([
        'donor_name' => 'Meera Shah',
        'item_title' => 'Morning Breakfast',
    ]);

    DonationOrder::create([
        'donation_subscription_id' => $subscription->id,
        'billing_cycle_number' => 1,
        'is_recurring' => true,
        'payment_provider' => 'razorpay',
        'provider_payment_id' => 'pay_sub_cycle_1',
        'donor_name' => $subscription->donor_name,
        'donor_email' => $subscription->donor_email,
        'donor_phone' => $subscription->donor_phone,
        'currency' => 'INR',
        'total_amount' => $subscription->total_amount,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.subscriptions.show', $subscription))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Subscriptions/Show')
            ->where('subscription.donor.name', 'Meera Shah')
            ->where('subscription.item_title', 'Morning Breakfast')
            ->has('orders', 1)
            ->where('orders.0.billing_cycle_number', 1));
});

it('shows attribution source on subscription details', function () {
    $user = createSubscriptionsAdminUser();

    $partner = User::factory()->create([
        'name' => 'Ashvini Partner',
        'referral_code' => 'ashvini',
    ]);

    $subscription = DonationSubscription::factory()->active()->create([
        'donor_name' => 'Meera Shah',
        'item_title' => 'Morning Breakfast',
        'source_channel' => DonationAttributionService::CHANNEL_WEB,
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'Ashvini | 20/08 | Sadbhavna | Bday',
        'utm_content' => 'Ashvini',
        'utm_term' => '12022952986830236',
        'attr_source' => 'meta',
        'attr_medium' => 'paid_social',
        'attr_platform' => 'instagram',
        'attr_placement' => 'instagram_reels',
        'partner_user_id' => $partner->id,
        'partner_code' => 'ashvini',
        'meta_campaign_id' => '120229544847593136',
        'meta_adset_id' => '120229532289290236',
        'meta_ad_id' => '12022952986830236',
        'landing_path' => '/donate/old-age-home',
        'device_type' => 'mobile',
    ]);

    actingAs($user)
        ->get(route('admin.subscriptions.show', $subscription))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Subscriptions/Show')
            ->where('subscription.source.channel', DonationAttributionService::CHANNEL_WEB)
            ->where('subscription.source.channel_label', 'Web checkout')
            ->where('subscription.source.attr_source_label', 'Meta · Instagram · Paid social')
            ->where('subscription.source.utm_source', 'meta')
            ->where('subscription.source.utm_campaign', 'Ashvini | 20/08 | Sadbhavna | Bday')
            ->where('subscription.source.utm_content', 'Ashvini')
            ->where('subscription.source.partner_code', 'ashvini')
            ->where('subscription.source.partner_name', 'Ashvini Partner')
            ->where('subscription.source.meta_campaign_id', '120229544847593136')
            ->where('subscription.source.landing_path', '/donate/old-age-home')
            ->where('subscription.source.device_type', 'mobile'));
});

it('forbids subscription pages without permission', function () {
    $user = User::factory()->create();
    $subscription = DonationSubscription::factory()->create();

    actingAs($user)
        ->get(route('admin.subscriptions.index'))
        ->assertForbidden();

    actingAs($user)
        ->get(route('admin.subscriptions.show', $subscription))
        ->assertForbidden();
});

it('exports filtered subscriptions as csv', function () {
    $user = createSubscriptionsAdminUser();

    DonationSubscription::factory()->active()->create([
        'donor_name' => 'Export Me',
        'donor_email' => 'export@example.com',
    ]);

    $response = actingAs($user)->get(route('admin.subscriptions.export', [
        'search' => 'export@example.com',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Export Me');
    expect($content)->toContain('export@example.com');
});

it('cancels an active subscription for authorized admins', function () {
    Permission::firstOrCreate(['name' => 'view subscriptions', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage subscriptions', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['view subscriptions', 'manage subscriptions']);

    $user = User::factory()->create();
    $user->assignRole($role);

    $subscription = DonationSubscription::factory()->active()->create([
        'razorpay_subscription_id' => 'sub_cancel_test',
    ]);

    $this->mock(RazorpaySubscriptionService::class, function ($mock): void {
        $mock->shouldReceive('cancelSubscription')
            ->once()
            ->andReturnUsing(function (DonationSubscription $subscription) {
                $subscription->update([
                    'status' => DonationSubscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'ended_at' => now(),
                    'next_charge_at' => null,
                    'cancel_reason' => 'Donor requested',
                ]);

                return $subscription->fresh();
            });
    });

    actingAs($user)
        ->post(route('admin.subscriptions.cancel', $subscription), [
            'cancel_reason' => 'Donor requested',
        ])
        ->assertRedirect(route('admin.subscriptions.show', $subscription));

    expect($subscription->fresh()->status)->toBe(DonationSubscription::STATUS_CANCELLED);
});

it('marks subscription cancelled locally even when razorpay keeps it active until cycle end', function () {
    Permission::firstOrCreate(['name' => 'view subscriptions', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage subscriptions', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['view subscriptions', 'manage subscriptions']);

    $user = User::factory()->create();
    $user->assignRole($role);

    $subscription = DonationSubscription::factory()->active()->create([
        'razorpay_subscription_id' => 'sub_cycle_end_cancel',
        'next_charge_at' => now()->addMonth(),
    ]);

    $this->mock(RazorpaySubscriptionService::class, function ($mock): void {
        $mock->shouldReceive('cancelSubscription')
            ->once()
            ->andReturnUsing(function (DonationSubscription $subscription) {
                $subscription->update([
                    'status' => DonationSubscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'ended_at' => $subscription->next_charge_at,
                    'next_charge_at' => null,
                    'cancel_reason' => 'End of cycle',
                    'meta' => ['cancel_at_cycle_end' => true],
                ]);

                return $subscription->fresh();
            });
    });

    actingAs($user)
        ->post(route('admin.subscriptions.cancel', $subscription), [
            'cancel_reason' => 'End of cycle',
            'cancel_at_cycle_end' => '1',
        ])
        ->assertRedirect(route('admin.subscriptions.show', $subscription));

    $fresh = $subscription->fresh();

    expect($fresh->status)->toBe(DonationSubscription::STATUS_CANCELLED);
    expect($fresh->next_charge_at)->toBeNull();
});

it('syncs subscription status from razorpay', function () {
    Permission::firstOrCreate(['name' => 'view subscriptions', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage subscriptions', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['view subscriptions', 'manage subscriptions']);

    $user = User::factory()->create();
    $user->assignRole($role);

    $subscription = DonationSubscription::factory()->active()->create([
        'razorpay_subscription_id' => 'sub_sync_test',
    ]);

    $this->mock(RazorpaySubscriptionService::class, function ($mock) use ($subscription): void {
        $mock->shouldReceive('syncSubscriptionFromRazorpay')
            ->once()
            ->andReturnUsing(function () use ($subscription) {
                $subscription->update([
                    'status' => DonationSubscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'next_charge_at' => null,
                ]);

                return $subscription->fresh();
            });
    });

    actingAs($user)
        ->post(route('admin.subscriptions.sync', $subscription))
        ->assertRedirect(route('admin.subscriptions.show', $subscription));

    expect($subscription->fresh()->status)->toBe(DonationSubscription::STATUS_CANCELLED);
});

it('forbids cancelling subscriptions without manage subscriptions permission', function () {
    $user = createSubscriptionsAdminUser();
    $subscription = DonationSubscription::factory()->active()->create();

    actingAs($user)
        ->post(route('admin.subscriptions.cancel', $subscription))
        ->assertForbidden();
});
