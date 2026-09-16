<?php

use App\Models\AnalyticsEvent;
use App\Models\Cause;
use App\Models\DonationCampaign;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\User;
use App\Support\AdminCampaignStatsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage causes']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['manage causes']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('renders campaign stats with donation and subscription metrics', function () {
    $cause = Cause::factory()->create(['title' => 'Old Age Home']);
    $campaign = DonationCampaign::factory()->create([
        'cause_id' => $cause->id,
        'name' => 'Elder Meal',
        'slug' => 'donate-meal-100rs',
        'amount' => 100,
        'goal_amount' => 400,
        'cause_package_id' => null,
    ]);

    $subscription = DonationSubscription::factory()->create([
        'cause_id' => $cause->id,
        'donation_campaign_id' => $campaign->id,
        'item_title' => 'Elder Meal',
        'total_amount' => 100,
        'status' => DonationSubscription::STATUS_ACTIVE,
        'meta' => ['campaign' => ['id' => $campaign->id, 'slug' => $campaign->slug, 'name' => $campaign->name]],
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_payment_id' => 'pay_test_100',
        'donation_subscription_id' => $subscription->id,
        'is_recurring' => true,
        'donor_name' => 'Monil Vekariya',
        'donor_email' => 'vekariyamonil8@gmail.com',
        'donor_phone' => '7600280806',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $order->items()->create([
        'cause_id' => $cause->id,
        'donation_campaign_id' => $campaign->id,
        'cause' => $cause->slug,
        'title' => 'Elder Meal',
        'quantity' => 1,
        'unit_amount' => 100,
        'amount' => 100,
        'meta' => ['campaign' => ['id' => $campaign->id, 'slug' => $campaign->slug, 'name' => $campaign->name]],
    ]);

    AnalyticsEvent::query()->create([
        'event_type' => AnalyticsEvent::TYPE_VISIT_CAMPAIGN,
        'cause_id' => $cause->id,
        'path' => '/give/'.$campaign->slug,
    ]);

    actingAs($this->admin)
        ->get(route('admin.campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Campaigns/Show')
            ->where('campaign.name', 'Elder Meal')
            ->where('summary.revenue', 100)
            ->where('summary.paid_count', 1)
            ->where('summary.active_subscriptions', 1)
            ->where('summary.page_views', 1)
            ->where('summary.goal_amount', 400)
            ->where('summary.goal_progress_percent', 25)
            ->has('activeSubscribers', 1)
            ->has('recentDonations', 1));

    $metrics = AdminCampaignStatsData::metricsForCampaign($campaign);

    expect($metrics['revenue'])->toBe(100.0)
        ->and($metrics['active_subscriptions'])->toBe(1)
        ->and($metrics['goal_amount'])->toBe(400.0)
        ->and($metrics['goal_progress_percent'])->toBe(25.0);
});

it('lists campaign performance summary on the campaigns index', function () {
    $cause = Cause::factory()->create();
    $campaign = DonationCampaign::factory()->create([
        'cause_id' => $cause->id,
        'name' => 'Breakfast Seva',
        'amount' => 500,
        'goal_amount' => 2000,
        'cause_package_id' => null,
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_payment_id' => 'pay_test_500',
        'donor_name' => 'Test Donor',
        'donor_email' => 'test@example.com',
        'donor_phone' => '9999999999',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $order->items()->create([
        'cause_id' => $cause->id,
        'donation_campaign_id' => $campaign->id,
        'cause' => $cause->slug,
        'title' => 'Breakfast Seva',
        'quantity' => 1,
        'unit_amount' => 500,
        'amount' => 500,
    ]);

    actingAs($this->admin)
        ->get(route('admin.campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Campaigns/Index')
            ->where('campaigns.data.0.stats.revenue', 500)
            ->where('campaigns.data.0.stats.paid_count', 1)
            ->where('campaigns.data.0.goal_amount', 2000)
            ->where('campaigns.data.0.stats.goal_progress_percent', 25)
            ->where('campaigns.data.0.share_url', fn ($url) => str_contains($url, '/give/'.$campaign->slug)));
});

it('detects donors active in multiple campaigns', function () {
    $cause = Cause::factory()->create();
    $campaignA = DonationCampaign::factory()->create([
        'cause_id' => $cause->id,
        'name' => 'Campaign A',
        'amount' => 100,
        'cause_package_id' => null,
    ]);
    $campaignB = DonationCampaign::factory()->create([
        'cause_id' => $cause->id,
        'name' => 'Campaign B',
        'amount' => 200,
        'cause_package_id' => null,
    ]);

    DonationSubscription::factory()->create([
        'cause_id' => $cause->id,
        'donation_campaign_id' => $campaignA->id,
        'donor_email' => 'donor@example.com',
        'status' => DonationSubscription::STATUS_ACTIVE,
        'meta' => ['campaign' => ['id' => $campaignA->id, 'name' => 'Campaign A']],
    ]);

    DonationSubscription::factory()->create([
        'cause_id' => $cause->id,
        'donation_campaign_id' => $campaignB->id,
        'donor_email' => 'donor@example.com',
        'status' => DonationSubscription::STATUS_ACTIVE,
        'meta' => ['campaign' => ['id' => $campaignB->id, 'name' => 'Campaign B']],
    ]);

    $donors = AdminCampaignStatsData::donorsWithMultipleLiveCampaigns();

    expect($donors)->toHaveCount(1)
        ->and($donors[0]['donor_email'])->toBe('donor@example.com')
        ->and($donors[0]['campaigns'])->toHaveCount(2);
});
