<?php

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationCampaign;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows campaign goal progress on the public give page', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
    ]);

    $package = CausePackage::factory()->for($cause)->create([
        'amount' => 500,
        'allow_recurring' => true,
        'is_active' => true,
    ]);

    $campaign = DonationCampaign::factory()->forPackage($package)->create([
        'slug' => 'goal-campaign',
        'goal_amount' => 10000,
        'headline' => 'Help us reach the goal',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_goal_1',
        'donor_name' => 'Goal Donor',
        'donor_email' => 'goal@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 2500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'donation_campaign_id' => $campaign->id,
        'cause' => $cause->title,
        'title' => $package->title,
        'quantity' => 1,
        'unit_amount' => 2500,
        'amount' => 2500,
    ]);

    $this->get(route('donate.campaign', $campaign->slug))
        ->assertOk()
        ->assertSee('Campaign progress', false)
        ->assertSee('₹2,500 raised of ₹10,000 goal', false)
        ->assertSee('25%', false);
});

it('hides campaign progress when no goal is set', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
    ]);

    $package = CausePackage::factory()->for($cause)->create([
        'amount' => 500,
        'allow_recurring' => true,
        'is_active' => true,
    ]);

    $campaign = DonationCampaign::factory()->forPackage($package)->create([
        'slug' => 'no-goal-campaign',
        'goal_amount' => null,
    ]);

    $this->get(route('donate.campaign', $campaign->slug))
        ->assertOk()
        ->assertDontSee('Campaign progress', false);
});
