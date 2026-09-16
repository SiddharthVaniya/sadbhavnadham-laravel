<?php

use App\Models\AnalyticsEvent;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createCampaignAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage causes']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['manage causes']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function recurringCampaignSetup(array $causeOverrides = [], array $packageOverrides = []): array
{
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create(array_merge([
        'is_active' => true,
        'allow_recurring' => true,
        'title' => 'General Seva',
        'slug' => 'general-seva',
    ], $causeOverrides));

    $package = CausePackage::factory()->for($cause)->create(array_merge([
        'title' => 'Monthly Support',
        'amount' => 500,
        'allow_recurring' => true,
        'is_active' => true,
    ], $packageOverrides));

    $campaign = DonationCampaign::factory()->forPackage($package)->create([
        'slug' => 'meta-monthly-500',
        'headline' => 'Give monthly. Change lives daily.',
        'subheadline' => 'Your steady support helps us serve every day.',
    ]);

    return compact('cause', 'package', 'campaign');
}

it('renders an active campaign landing page with recurring checkout only', function () {
    ['campaign' => $campaign] = recurringCampaignSetup();

    $response = $this->get(route('donate.campaign', $campaign->slug));

    $response->assertOk();
    $response->assertSee('Give monthly. Change lives daily.', false);
    $response->assertSee('₹ 500', false);
    $response->assertSee('data-force-recurring="1"', false);
    $response->assertSee('data-lock-amount="1"', false);
    $response->assertSee('data-frequency="monthly"', false);
    $response->assertSee('Monthly gift', false);
    $response->assertSee('Billed per month', false);
    $response->assertSee('name="frequency" value="monthly"', false);
    $response->assertSee('name="amount_locked" value="1"', false);
    $response->assertSee('data-subscription-action', false);
    $response->assertDontSee('How would you like to give?', false);
    $response->assertDontSee('How often should we charge?', false);
    $response->assertDontSee('Donate from Outside India', false);

    expect(AnalyticsEvent::query()
        ->where('event_type', AnalyticsEvent::TYPE_VISIT_CAMPAIGN)
        ->where('cause_id', $campaign->cause_id)
        ->exists())->toBeTrue();
});

it('renders weekly cadence copy when campaign frequency is weekly', function () {
    ['cause' => $cause, 'campaign' => $campaign] = recurringCampaignSetup([
        'allow_weekly_recurring' => true,
    ]);
    $campaign->update(['frequency' => 'weekly']);

    $response = $this->get(route('donate.campaign', $campaign->slug));

    $response->assertOk();
    $response->assertSee('data-frequency="weekly"', false);
    $response->assertSee('Weekly gift', false);
    $response->assertSee('per week', false);
    $response->assertSee('Billed per week', false);
    $response->assertSee('Start Weekly Donation', false);
    $response->assertSee('name="frequency" value="weekly"', false);
});

it('hides recurring cadence card when campaign is one-time only', function () {
    ['campaign' => $campaign] = recurringCampaignSetup();
    $campaign->update([
        'recurring_only' => false,
        'amount' => 20,
    ]);

    $response = $this->get(route('donate.campaign', $campaign->slug));

    $response->assertOk();
    $response->assertSee('One-time gift', false);
    $response->assertDontSee('Billed per month', false);
    $response->assertDontSee('Billed per week', false);
});

it('renders one-time checkout when recurring only is disabled', function () {
    ['campaign' => $campaign] = recurringCampaignSetup();
    $campaign->update([
        'recurring_only' => false,
        'amount' => 20,
    ]);

    $response = $this->get(route('donate.campaign', $campaign->slug));

    $response->assertOk();
    $response->assertSee('data-force-recurring="0"', false);
    $response->assertSee('id="donationType" name="donation_type" value="one_time"', false);
    $response->assertDontSee('data-subscription-action', false);
    $response->assertSee('One-time gift', false);
});

it('returns 404 when campaign amount is outside subscription limits', function () {
    config([
        'payments.razorpay.subscriptions_enabled' => true,
        'payments.razorpay.subscription_min_amount' => 100,
    ]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
    ]);

    $campaign = DonationCampaign::factory()->create([
        'cause_id' => $cause->id,
        'cause_package_id' => null,
        'amount' => 10,
        'slug' => 'below-minimum-live',
    ]);

    $this->get(route('donate.campaign', $campaign->slug))->assertNotFound();
});

it('rejects admin campaign creation for inactive causes', function () {
    $user = createCampaignAdmin();
    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'is_active' => false,
    ]);

    actingAs($user)
        ->post(route('admin.campaigns.store'), [
            'name' => 'Inactive cause campaign',
            'slug' => 'inactive-cause',
            'cause_id' => $cause->id,
            'amount' => 500,
            'recurring_only' => true,
            'frequency' => 'monthly',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('cause_id');
});

it('stores billing frequency when admin creates a weekly campaign', function () {
    $user = createCampaignAdmin();
    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'allow_weekly_recurring' => true,
        'is_active' => true,
    ]);

    actingAs($user)
        ->post(route('admin.campaigns.store'), [
            'name' => 'Weekly seva',
            'slug' => 'weekly-seva',
            'cause_id' => $cause->id,
            'amount' => 100,
            'recurring_only' => true,
            'frequency' => 'weekly',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.campaigns.index'));

    $campaign = DonationCampaign::query()->where('slug', 'weekly-seva')->first();

    expect($campaign)->not->toBeNull()
        ->and($campaign->frequency)->toBe('weekly')
        ->and($campaign->billingFrequency())->toBe('weekly')
        ->and($campaign->frequencyLabel())->toBe('Weekly');
});

it('returns 404 for inactive or expired campaigns', function () {
    ['campaign' => $campaign] = recurringCampaignSetup();

    $campaign->update(['is_active' => false]);

    $this->get(route('donate.campaign', $campaign->slug))->assertNotFound();

    $campaign->update([
        'is_active' => true,
        'ends_at' => now()->subDay(),
    ]);

    $this->get(route('donate.campaign', $campaign->slug))->assertNotFound();
});

it('returns 404 when subscriptions are disabled', function () {
    ['campaign' => $campaign] = recurringCampaignSetup();

    config(['payments.razorpay.subscriptions_enabled' => false]);

    $this->get(route('donate.campaign', $campaign->slug))->assertNotFound();
});

it('does not change the regular cause donation page behavior', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
        'allow_custom_amount' => true,
    ]);

    CausePackage::factory()->for($cause)->create([
        'title' => 'Breakfast Seva',
        'amount' => 500,
        'allow_recurring' => true,
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('How would you like to give?', false);
    $response->assertSee('data-force-recurring="0"', false);
    $response->assertSee('data-lock-amount="0"', false);
    $response->assertSee('Donate from Outside India', false);
    $response->assertSee('id="donationType" name="donation_type" value="one_time"', false);
});

it('lets admins create a campaign with a dynamic cause selection', function () {
    $user = createCampaignAdmin();
    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'title' => 'Education Fund',
    ]);

    actingAs($user)
        ->post(route('admin.campaigns.store'), [
            'name' => 'Meta India July',
            'slug' => 'meta-india-july',
            'cause_id' => $cause->id,
            'amount' => 500,
            'title' => 'Monthly Education Support',
            'headline' => 'Support education every month',
            'recurring_only' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.campaigns.index'));

    $campaign = DonationCampaign::query()->where('slug', 'meta-india-july')->first();

    expect($campaign)->not->toBeNull()
        ->and($campaign->cause_id)->toBe($cause->id)
        ->and((float) $campaign->amount)->toBe(500.0);
});

it('validates that the selected package belongs to the chosen cause', function () {
    $user = createCampaignAdmin();
    $cause = Cause::factory()->create(['allow_recurring' => true]);
    $otherCause = Cause::factory()->create(['allow_recurring' => true]);
    $foreignPackage = CausePackage::factory()->for($otherCause)->create([
        'allow_recurring' => true,
        'is_active' => true,
    ]);

    actingAs($user)
        ->post(route('admin.campaigns.store'), [
            'name' => 'Invalid package campaign',
            'slug' => 'invalid-package',
            'cause_id' => $cause->id,
            'cause_package_id' => $foreignPackage->id,
            'recurring_only' => true,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('cause_package_id');
});

it('uses subscription amount limits from config for custom campaign amounts', function () {
    config([
        'payments.razorpay.subscription_min_amount' => 100,
        'payments.razorpay.subscription_max_amount' => 15000,
    ]);

    $user = createCampaignAdmin();
    $cause = Cause::factory()->create(['allow_recurring' => true]);

    actingAs($user)
        ->post(route('admin.campaigns.store'), [
            'name' => 'Below minimum',
            'slug' => 'below-minimum',
            'cause_id' => $cause->id,
            'amount' => 10,
            'recurring_only' => true,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('amount');

    actingAs($user)
        ->post(route('admin.campaigns.store'), [
            'name' => 'Valid amount',
            'slug' => 'valid-amount',
            'cause_id' => $cause->id,
            'amount' => 500,
            'recurring_only' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.campaigns.index'));
});

it('rejects packages outside subscription amount limits', function () {
    config([
        'payments.razorpay.subscription_min_amount' => 100,
        'payments.razorpay.subscription_max_amount' => 15000,
    ]);

    $user = createCampaignAdmin();
    $cause = Cause::factory()->create(['allow_recurring' => true]);
    $package = CausePackage::factory()->for($cause)->create([
        'amount' => 50,
        'allow_recurring' => true,
        'is_active' => true,
    ]);

    actingAs($user)
        ->post(route('admin.campaigns.store'), [
            'name' => 'Low package campaign',
            'slug' => 'low-package',
            'cause_id' => $cause->id,
            'cause_package_id' => $package->id,
            'recurring_only' => true,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('cause_package_id');
});

it('lets admins update an existing campaign', function () {
    $user = createCampaignAdmin();
    ['campaign' => $campaign, 'cause' => $cause, 'package' => $package] = recurringCampaignSetup([
        'slug' => 'adopt-a-parent',
    ]);

    actingAs($user)
        ->put(route('admin.campaigns.update', $campaign), [
            'name' => 'Old Age Home',
            'slug' => 'donate-meal',
            'cause_id' => $cause->id,
            'cause_package_id' => $package->id,
            'headline' => 'Support elders every month',
            'recurring_only' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.campaigns.index'));

    $campaign->refresh();

    expect($campaign->slug)->toBe('donate-meal')
        ->and($campaign->headline)->toBe('Support elders every month');
});

it('lets admins remove a campaign image on update', function () {
    $user = createCampaignAdmin();
    ['campaign' => $campaign, 'cause' => $cause, 'package' => $package] = recurringCampaignSetup();

    $campaign->update(['image' => 'storage/campaigns/images/old.jpg']);

    actingAs($user)
        ->put(route('admin.campaigns.update', $campaign), [
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'cause_id' => $cause->id,
            'cause_package_id' => $package->id,
            'remove_image' => true,
            'image_existing' => 'storage/campaigns/images/old.jpg',
            'recurring_only' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.campaigns.index'));

    expect($campaign->fresh()->image)->toBeNull();
});

it('renders admin campaign pages with cause options', function () {
    $user = createCampaignAdmin();
    $cause = Cause::factory()->create(['allow_recurring' => true, 'title' => 'Health Camp']);
    CausePackage::factory()->for($cause)->create([
        'title' => 'Monthly Health',
        'amount' => 300,
        'allow_recurring' => true,
    ]);

    actingAs($user)
        ->get(route('admin.campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Campaigns/Index'));

    actingAs($user)
        ->get(route('admin.campaigns.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Campaigns/Form')
            ->has('causes', 1)
            ->where('causes.0.title', 'Health Camp')
            ->where('causes.0.hero_image', fn ($value) => $value === null || is_string($value)));
});

it('uses campaign image when set and falls back to cause hero image', function () {
    ['campaign' => $campaign] = recurringCampaignSetup([
        'hero_image' => 'storage/causes/hero/fallback.jpg',
    ]);

    $campaign->update(['image' => null]);

    $this->get(route('donate.campaign', $campaign->slug))
        ->assertOk()
        ->assertSee('storage/causes/hero/fallback.jpg', false);

    $campaign->update(['image' => 'storage/campaigns/images/custom.jpg']);

    $this->get(route('donate.campaign', $campaign->slug))
        ->assertOk()
        ->assertSee('storage/campaigns/images/custom.jpg', false);
});

it('resolves hero image with campaign override via model helper', function () {
    ['campaign' => $campaign] = recurringCampaignSetup([
        'hero_image' => 'storage/causes/hero/cause.jpg',
    ]);

    expect($campaign->resolvedHeroImage())->toBe('storage/causes/hero/cause.jpg');

    $campaign->update(['image' => 'storage/campaigns/images/campaign.jpg']);

    expect($campaign->fresh()->resolvedHeroImage())->toBe('storage/campaigns/images/campaign.jpg');
});

it('uses campaign name as donation title when no override is set', function () {
    ['campaign' => $campaign] = recurringCampaignSetup();

    $campaign->update([
        'name' => 'Elder Meal',
        'title' => null,
        'cause_package_id' => null,
        'amount' => 100,
    ]);

    expect($campaign->fresh()->resolvedTitle())->toBe('Elder Meal');

    $response = $this->get(route('donate.campaign', $campaign->slug));

    $response->assertOk();
    $response->assertSee('name="title" value="Elder Meal"', false);
    $response->assertSee('name="campaign_slug" value="'.$campaign->slug.'"', false);
});
