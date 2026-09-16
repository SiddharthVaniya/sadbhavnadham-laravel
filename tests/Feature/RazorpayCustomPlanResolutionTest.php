<?php

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\RazorpayPlan;
use App\Services\RazorpaySubscriptionService;
use App\Support\SubscriptionFrequency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reuses an existing custom razorpay plan instead of creating a duplicate', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'allow_custom_amount' => true,
    ]);

    $existing = RazorpayPlan::factory()->create([
        'cause_id' => $cause->id,
        'cause_package_id' => null,
        'frequency' => SubscriptionFrequency::MONTHLY,
        'amount' => 300,
        'razorpay_plan_id' => 'plan_existing_300',
        'plan_name' => 'Existing custom plan',
    ]);

    $resolved = app(RazorpaySubscriptionService::class)
        ->resolveCustomAmountPlan($cause, SubscriptionFrequency::MONTHLY, 300.0);

    expect($resolved->id)->toBe($existing->id)
        ->and(RazorpayPlan::query()->where('cause_id', $cause->id)->count())->toBe(1);
});

it('allows a custom plan to coexist with a package plan of the same amount', function () {
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->for($cause)->create(['amount' => 300]);

    RazorpayPlan::factory()->create([
        'cause_id' => $cause->id,
        'cause_package_id' => $package->id,
        'frequency' => SubscriptionFrequency::MONTHLY,
        'amount' => 300,
        'razorpay_plan_id' => 'plan_package_300',
    ]);

    RazorpayPlan::factory()->create([
        'cause_id' => $cause->id,
        'cause_package_id' => null,
        'frequency' => SubscriptionFrequency::MONTHLY,
        'amount' => 300,
        'razorpay_plan_id' => 'plan_custom_300',
        'plan_name' => 'Dog Shelter - Custom ₹300 (Monthly)',
    ]);

    expect(RazorpayPlan::query()->count())->toBe(2);
});

it('matches existing custom plans when amount has float noise', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'allow_recurring' => true,
        'allow_custom_amount' => true,
    ]);

    $existing = RazorpayPlan::factory()->create([
        'cause_id' => $cause->id,
        'cause_package_id' => null,
        'frequency' => SubscriptionFrequency::MONTHLY,
        'amount' => 300.00,
        'razorpay_plan_id' => 'plan_float_300',
    ]);

    $resolved = app(RazorpaySubscriptionService::class)
        ->resolveCustomAmountPlan($cause, SubscriptionFrequency::MONTHLY, 300.0000001);

    expect($resolved->id)->toBe($existing->id);
});
