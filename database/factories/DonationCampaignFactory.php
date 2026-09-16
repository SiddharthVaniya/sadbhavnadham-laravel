<?php

namespace Database\Factories;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DonationCampaignFactory extends Factory
{
    protected $model = DonationCampaign::class;

    public function definition(): array
    {
        $name = 'Meta Monthly '.$this->faker->unique()->randomNumber(3);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'cause_id' => Cause::factory(),
            'cause_package_id' => null,
            'amount' => 500,
            'title' => 'Monthly Support',
            'headline' => 'Support our mission every month',
            'subheadline' => 'Your monthly gift helps us serve consistently.',
            'recurring_only' => true,
            'frequency' => 'monthly',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function forPackage(CausePackage $package): static
    {
        return $this->state(fn (): array => [
            'cause_id' => $package->cause_id,
            'cause_package_id' => $package->id,
            'amount' => null,
            'title' => null,
        ]);
    }
}
