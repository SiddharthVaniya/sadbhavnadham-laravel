<?php

use App\Models\AnalyticsEvent;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationCampaign;
use App\Models\DonationSubscription;
use App\Models\RazorpayPlan;
use App\Services\RazorpaySubscriptionService;
use App\Support\SubscriptionFrequency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function recurringDonationPayload(Cause $cause, CausePackage $package, array $overrides = []): array
{
    return array_merge([
        'cause' => $cause->slug,
        'package_id' => $package->id,
        'frequency' => SubscriptionFrequency::MONTHLY,
        'quantity' => 1,
        'title' => $package->title,
        'donor_name' => 'Asha Patel',
        'donor_email' => 'asha@example.com',
        'donor_phone' => '9876543210',
        'address' => '123 Main Street',
        'pincode' => '380015',
        'city' => 'Ahmedabad',
        'state' => 'Gujarat',
        'country' => 'INDIA',
        'donor_country' => 'IN',
        'consent_indian_citizen' => '1',
        'consent_recurring' => '1',
        'pan_number' => 'ABCDE1234F',
    ], $overrides);
}

it('shows monthly donation toggle when recurring is enabled for the cause', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
    ]);

    CausePackage::factory()->for($cause)->create([
        'title' => 'Morning Breakfast',
        'amount' => 500,
        'allow_recurring' => true,
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('How would you like to give?', false);
    $response->assertSee('Every month', false);
    $response->assertDontSee('Every week', false);
    $response->assertSee('data-subscription-action', false);
});

it('shows weekly donation option when weekly recurring is enabled for the cause', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
        'allow_weekly_recurring' => true,
        'allow_custom_amount' => true,
    ]);

    CausePackage::factory()->for($cause)->create([
        'title' => 'Morning Breakfast',
        'amount' => 500,
        'allow_recurring' => true,
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('How would you like to give?', false);
    $response->assertSee('Every month', false);
    $response->assertSee('Every week', false);
    $response->assertSee('data-frequency="weekly"', false);
    $response->assertSee('data-tab-count="3"', false);
});

it('shows only weekly when monthly is off and weekly is on', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => false,
        'allow_weekly_recurring' => true,
        'allow_custom_amount' => true,
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('How would you like to give?', false);
    $response->assertDontSee('Every month', false);
    $response->assertSee('Every week', false);
    $response->assertSee('data-tab-count="2"', false);
});

it('rejects weekly checkout when the cause does not allow weekly recurring', function () {
    config([
        'payments.razorpay.subscriptions_enabled' => true,
        'payments.razorpay.key' => 'rzp_test_key',
    ]);

    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'allow_weekly_recurring' => false,
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);

    $package = CausePackage::factory()->for($cause)->create([
        'allow_recurring' => true,
        'amount' => 500,
    ]);

    $this->postJson(route('donate.razorpay.subscription'), recurringDonationPayload($cause, $package, [
        'frequency' => SubscriptionFrequency::WEEKLY,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['frequency']);
});

it('hides monthly donation toggle when no packages allow recurring and custom amount is disabled', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
        'allow_custom_amount' => false,
    ]);

    CausePackage::factory()->for($cause)->create([
        'allow_recurring' => false,
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertDontSee('How would you like to give?', false);
    $response->assertDontSee('data-subscription-action', false);
});

it('shows monthly donation toggle when custom amount is allowed even without recurring packages', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
        'allow_custom_amount' => true,
    ]);

    CausePackage::factory()->for($cause)->create([
        'allow_recurring' => false,
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('How would you like to give?', false);
    $response->assertSee('Every month', false);
    $response->assertSee('data-subscription-action', false);
});

it('renders cause contact card when contact details are configured', function () {
    $cause = Cause::factory()->create([
        'is_active' => true,
        'contact_heading' => 'Tree Plantation Office',
        'contact_address' => "Sadbhavna Vrudhashram\nAnand, Gujarat",
        'contact_phone' => '+91 98765 43210',
        'contact_email' => 'trees@sadbhavna.org',
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('cause-contact-card', false);
    $response->assertSee('Tree Plantation Office', false);
    $response->assertSee('Sadbhavna Vrudhashram', false);
    $response->assertSee('trees@sadbhavna.org', false);
});

it('starts recurring checkout with a custom monthly amount', function () {
    config([
        'payments.razorpay.subscriptions_enabled' => true,
        'payments.razorpay.key' => 'rzp_test_key',
    ]);

    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);

    $plan = RazorpayPlan::factory()->create([
        'cause_id' => $cause->id,
        'cause_package_id' => null,
        'frequency' => SubscriptionFrequency::MONTHLY,
        'amount' => 2500,
        'razorpay_plan_id' => 'plan_custom2500',
    ]);

    $this->mock(RazorpaySubscriptionService::class, function ($mock) use ($plan, $cause): void {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('resolveTotalCountForFrequency')
            ->with(SubscriptionFrequency::MONTHLY)
            ->andReturn(360);
        $mock->shouldReceive('resolvePlanForDonation')
            ->once()
            ->with(
                \Mockery::on(fn ($arg) => $arg->is($cause)),
                SubscriptionFrequency::MONTHLY,
                2500.0,
                null,
                false,
            )
            ->andReturn($plan);
        $mock->shouldReceive('createRazorpaySubscription')
            ->once()
            ->andReturnUsing(function (DonationSubscription $subscription, RazorpayPlan $plan) {
                $subscription->update([
                    'razorpay_plan_id' => $plan->razorpay_plan_id,
                    'razorpay_subscription_id' => 'sub_custom123',
                ]);

                return [
                    'id' => 'sub_custom123',
                    'status' => 'created',
                ];
            });
    });

    $response = $this->postJson(
        route('donate.razorpay.subscription'),
        [
            'cause' => $cause->slug,
            'amount' => 2500,
            'frequency' => SubscriptionFrequency::MONTHLY,
            'quantity' => 1,
            'title' => 'Monthly Donation',
            'donor_name' => 'Asha Patel',
            'donor_email' => 'asha@example.com',
            'donor_phone' => '9876543210',
            'address' => '123 Main Street',
            'pincode' => '380015',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'country' => 'INDIA',
            'donor_country' => 'IN',
            'consent_indian_citizen' => '1',
            'consent_recurring' => '1',
        ]
    );

    $response->assertOk()
        ->assertJson([
            'provider' => 'razorpay',
            'mode' => 'subscription',
            'subscription' => [
                'subscription_id' => 'sub_custom123',
            ],
        ]);

    $this->assertDatabaseHas('donation_subscriptions', [
        'cause_id' => $cause->id,
        'cause_package_id' => null,
        'unit_amount' => 2500,
        'donor_email' => 'asha@example.com',
        'razorpay_plan_id' => 'plan_custom2500',
    ]);
});

it('rejects recurring checkout without package or custom amount', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);

    $response = $this->postJson(
        route('donate.razorpay.subscription'),
        [
            'cause' => $cause->slug,
            'frequency' => SubscriptionFrequency::MONTHLY,
            'quantity' => 1,
            'donor_name' => 'Asha Patel',
            'donor_email' => 'asha@example.com',
            'donor_phone' => '9876543210',
            'address' => '123 Main Street',
            'pincode' => '380015',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'country' => 'INDIA',
            'donor_country' => 'IN',
            'consent_indian_citizen' => '1',
            'consent_recurring' => '1',
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('hides monthly donation toggle when recurring is disabled', function () {
    config(['payments.razorpay.subscriptions_enabled' => false]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
    ]);

    CausePackage::factory()->for($cause)->create();

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertDontSee('How would you like to give?', false);
    $response->assertDontSee('data-subscription-action', false);
});

it('rejects recurring checkout when subscriptions are disabled', function () {
    config(['payments.razorpay.subscriptions_enabled' => false]);

    $cause = Cause::factory()->create(['allow_recurring' => true]);
    $package = CausePackage::factory()->for($cause)->create([
        'amount' => 500,
        'allow_recurring' => true,
    ]);

    $response = $this->postJson(
        route('donate.razorpay.subscription'),
        recurringDonationPayload($cause, $package)
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['donation_type']);
});

it('rejects recurring checkout when cause does not allow recurring', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'allow_recurring' => false,
        'pan_required' => false,
    ]);
    $package = CausePackage::factory()->for($cause)->create([
        'amount' => 500,
        'allow_recurring' => true,
    ]);

    $response = $this->postJson(
        route('donate.razorpay.subscription'),
        recurringDonationPayload($cause, $package, ['pan_number' => null])
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['cause']);
});

it('rejects recurring checkout when package does not allow recurring', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'pan_required' => false,
    ]);
    $package = CausePackage::factory()->for($cause)->create([
        'amount' => 500,
        'allow_recurring' => false,
    ]);

    $response = $this->postJson(
        route('donate.razorpay.subscription'),
        recurringDonationPayload($cause, $package, ['pan_number' => null])
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['package_id']);
});

it('requires recurring mandate consent', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create(['allow_recurring' => true]);
    $package = CausePackage::factory()->for($cause)->create([
        'amount' => 500,
        'allow_recurring' => true,
    ]);

    $response = $this->postJson(
        route('donate.razorpay.subscription'),
        recurringDonationPayload($cause, $package, ['consent_recurring' => null])
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['consent_recurring']);
});

it('starts recurring checkout for campaign fixed amounts without custom amount enabled on cause', function () {
    config([
        'payments.razorpay.subscriptions_enabled' => true,
        'payments.razorpay.key' => 'rzp_test_key',
        'payments.razorpay.subscription_min_amount' => 1,
        'payments.razorpay.subscription_max_amount' => 15000,
    ]);

    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'allow_custom_amount' => false,
        'pan_required' => false,
    ]);

    $plan = RazorpayPlan::factory()->create([
        'cause_id' => $cause->id,
        'cause_package_id' => null,
        'frequency' => SubscriptionFrequency::MONTHLY,
        'amount' => 10,
        'razorpay_plan_id' => 'plan_campaign10',
    ]);

    $this->mock(RazorpaySubscriptionService::class, function ($mock) use ($plan, $cause): void {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('resolveTotalCountForFrequency')
            ->with(SubscriptionFrequency::MONTHLY)
            ->andReturn(360);
        $mock->shouldReceive('resolvePlanForDonation')
            ->once()
            ->with(
                \Mockery::on(fn ($arg) => $arg->is($cause)),
                SubscriptionFrequency::MONTHLY,
                10.0,
                null,
                true,
            )
            ->andReturn($plan);
        $mock->shouldReceive('createRazorpaySubscription')
            ->once()
            ->andReturn([
                'id' => 'sub_campaign10',
                'status' => 'created',
            ]);
    });

    $response = $this->postJson(
        route('donate.razorpay.subscription'),
        [
            'cause' => $cause->slug,
            'amount' => 10,
            'amount_locked' => '1',
            'frequency' => SubscriptionFrequency::MONTHLY,
            'quantity' => 1,
            'title' => 'Monthly Donation',
            'donor_name' => 'Asha Patel',
            'donor_email' => 'asha@example.com',
            'donor_phone' => '9876543210',
            'address' => '123 Main Street',
            'pincode' => '380015',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'country' => 'INDIA',
            'donor_country' => 'IN',
            'consent_indian_citizen' => '1',
            'consent_recurring' => '1',
        ]
    );

    $response->assertSuccessful()
        ->assertJsonPath('mode', 'subscription')
        ->assertJsonPath('subscription.subscription_id', 'sub_campaign10');
});

it('starts recurring checkout and returns razorpay subscription payload', function () {
    config([
        'payments.razorpay.subscriptions_enabled' => true,
        'payments.razorpay.key' => 'rzp_test_key',
    ]);

    $cause = Cause::factory()->create(['allow_recurring' => true]);
    $package = CausePackage::factory()->for($cause)->create([
        'title' => 'Morning Breakfast',
        'amount' => 500,
        'allow_recurring' => true,
    ]);

    $plan = RazorpayPlan::factory()->create([
        'cause_package_id' => $package->id,
        'frequency' => SubscriptionFrequency::MONTHLY,
        'amount' => 500,
        'razorpay_plan_id' => 'plan_test123',
    ]);

    $this->mock(RazorpaySubscriptionService::class, function ($mock) use ($plan, $cause, $package): void {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('resolveTotalCountForFrequency')
            ->with(SubscriptionFrequency::MONTHLY)
            ->andReturn(360);
        $mock->shouldReceive('resolvePlanForDonation')
            ->once()
            ->with(
                \Mockery::on(fn ($arg) => $arg->is($cause)),
                SubscriptionFrequency::MONTHLY,
                500.0,
                \Mockery::on(fn ($arg) => $arg->is($package)),
                false,
            )
            ->andReturn($plan);
        $mock->shouldReceive('createRazorpaySubscription')
            ->once()
            ->andReturnUsing(function (DonationSubscription $subscription, RazorpayPlan $plan) {
                $subscription->update([
                    'razorpay_plan_id' => $plan->razorpay_plan_id,
                    'razorpay_subscription_id' => 'sub_test123',
                ]);

                return [
                    'id' => 'sub_test123',
                    'status' => 'created',
                ];
            });
    });

    $response = $this->postJson(
        route('donate.razorpay.subscription'),
        recurringDonationPayload($cause, $package)
    );

    $response->assertOk()
        ->assertJson([
            'provider' => 'razorpay',
            'mode' => 'subscription',
            'subscription' => [
                'subscription_id' => 'sub_test123',
                'key' => 'rzp_test_key',
                'name' => 'Asha Patel',
                'email' => 'asha@example.com',
                'contact' => '9876543210',
            ],
        ]);

    $this->assertDatabaseHas('donation_subscriptions', [
        'cause_id' => $cause->id,
        'cause_package_id' => $package->id,
        'donor_email' => 'asha@example.com',
        'frequency' => SubscriptionFrequency::MONTHLY,
        'consent_recurring' => true,
        'razorpay_plan_id' => 'plan_test123',
        'razorpay_subscription_id' => 'sub_test123',
    ]);

    $this->assertDatabaseHas('analytics_events', [
        'event_type' => AnalyticsEvent::TYPE_SUBSCRIPTION_CHECKOUT_STARTED,
        'cause_id' => $cause->id,
    ]);
});

it('stores campaign name instead of generic monthly donation title', function () {
    config([
        'payments.razorpay.subscriptions_enabled' => true,
        'payments.razorpay.key' => 'rzp_test_key',
    ]);

    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'allow_custom_amount' => false,
        'pan_required' => false,
    ]);

    $plan = RazorpayPlan::factory()->create([
        'cause_id' => $cause->id,
        'cause_package_id' => null,
        'frequency' => SubscriptionFrequency::MONTHLY,
        'amount' => 100,
        'razorpay_plan_id' => 'plan_elder_meal',
    ]);

    $this->mock(RazorpaySubscriptionService::class, function ($mock) use ($plan, $cause): void {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('resolveTotalCountForFrequency')
            ->with(SubscriptionFrequency::MONTHLY)
            ->andReturn(360);
        $mock->shouldReceive('resolvePlanForDonation')
            ->once()
            ->with(
                \Mockery::on(fn ($arg) => $arg->is($cause)),
                SubscriptionFrequency::MONTHLY,
                100.0,
                null,
                true,
            )
            ->andReturn($plan);
        $mock->shouldReceive('createRazorpaySubscription')
            ->once()
            ->andReturn([
                'id' => 'sub_elder_meal',
                'status' => 'created',
            ]);
    });

    $this->postJson(route('donate.razorpay.subscription'), [
        'cause' => $cause->slug,
        'amount' => 100,
        'amount_locked' => '1',
        'title' => 'Elder Meal',
        'campaign_slug' => 'donate-meal-100rs',
        'frequency' => SubscriptionFrequency::MONTHLY,
        'quantity' => 1,
        'donor_name' => 'Monil Vekariya',
        'donor_email' => 'vekariyamonil8@gmail.com',
        'donor_phone' => '7600280806',
        'address' => 'Tramba',
        'pincode' => '360020',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'country' => 'INDIA',
        'donor_country' => 'IN',
        'consent_indian_citizen' => '1',
        'consent_recurring' => '1',
    ])->assertSuccessful();

    $this->assertDatabaseHas('donation_subscriptions', [
        'donor_email' => 'vekariyamonil8@gmail.com',
        'item_title' => 'Elder Meal',
    ]);
});

it('rejects a frequency that does not match the campaign billing cadence', function () {
    config([
        'payments.razorpay.subscriptions_enabled' => true,
        'payments.razorpay.key' => 'rzp_test_key',
    ]);

    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'allow_weekly_recurring' => true,
        'allow_custom_amount' => false,
        'pan_required' => false,
        'is_active' => true,
    ]);

    DonationCampaign::factory()->create([
        'cause_id' => $cause->id,
        'cause_package_id' => null,
        'slug' => 'weekly-locked',
        'amount' => 100,
        'recurring_only' => true,
        'frequency' => SubscriptionFrequency::WEEKLY,
        'is_active' => true,
    ]);

    $this->postJson(route('donate.razorpay.subscription'), [
        'cause' => $cause->slug,
        'amount' => 100,
        'amount_locked' => '1',
        'title' => 'Weekly Seva',
        'campaign_slug' => 'weekly-locked',
        'frequency' => SubscriptionFrequency::MONTHLY,
        'quantity' => 1,
        'donor_name' => 'Monil Vekariya',
        'donor_email' => 'weekly-mismatch@example.com',
        'donor_phone' => '7600280806',
        'address' => 'Tramba',
        'pincode' => '360020',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'country' => 'INDIA',
        'donor_country' => 'IN',
        'consent_indian_citizen' => '1',
        'consent_recurring' => '1',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['frequency']);
});
