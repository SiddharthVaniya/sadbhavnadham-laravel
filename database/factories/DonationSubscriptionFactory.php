<?php

namespace Database\Factories;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationSubscription;
use App\Models\Donor;
use App\Support\SubscriptionFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;

class DonationSubscriptionFactory extends Factory
{
    protected $model = DonationSubscription::class;

    public function definition(): array
    {
        $unitAmount = fake()->randomFloat(2, 500, 5000);
        $quantity = 1;

        return [
            'donor_id' => Donor::factory(),
            'cause_id' => Cause::factory()->state(['allow_recurring' => true]),
            'cause_package_id' => function (array $attributes) {
                return CausePackage::factory()->create([
                    'cause_id' => $attributes['cause_id'],
                ])->id;
            },
            'frequency' => SubscriptionFrequency::MONTHLY,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'total_amount' => $unitAmount * $quantity,
            'currency' => 'INR',
            'item_title' => fake()->words(3, true),
            'razorpay_plan_id' => 'plan_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'razorpay_subscription_id' => 'sub_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'status' => DonationSubscription::STATUS_CREATED,
            'billing_cycle_count' => 0,
            'donor_name' => fake()->name(),
            'donor_email' => fake()->safeEmail(),
            'donor_phone' => '9'.fake()->numerify('#########'),
            'date_of_birth' => fake()->optional()->date(),
            'pan_number' => fake()->optional()->regexify('[A-Z]{5}[0-9]{4}[A-Z]{1}'),
            'address' => fake()->address(),
            'pincode' => fake()->numerify('######'),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'INDIA',
            'donor_country_code' => 'IN',
            'consent_indian_citizen' => true,
            'consent_recurring' => true,
            'meta' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => DonationSubscription::STATUS_ACTIVE,
            'started_at' => now()->subMonth(),
            'next_charge_at' => now()->addMonth(),
            'billing_cycle_count' => 1,
        ]);
    }
}
