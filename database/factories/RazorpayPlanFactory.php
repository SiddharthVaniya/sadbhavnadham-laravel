<?php

namespace Database\Factories;

use App\Models\CausePackage;
use App\Models\RazorpayPlan;
use App\Support\SubscriptionFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;

class RazorpayPlanFactory extends Factory
{
    protected $model = RazorpayPlan::class;

    public function definition(): array
    {
        return [
            'cause_package_id' => CausePackage::factory(),
            'frequency' => SubscriptionFrequency::MONTHLY,
            'amount' => fake()->randomFloat(2, 500, 5000),
            'currency' => 'INR',
            'razorpay_plan_id' => 'plan_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'plan_name' => fake()->sentence(4),
        ];
    }
}
