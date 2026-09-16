<?php

namespace Database\Factories;

use App\Models\Cause;
use App\Models\CausePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CausePackageFactory extends Factory
{
    protected $model = CausePackage::class;

    public function definition(): array
    {
        return [
            'cause_id' => Cause::factory(),
            'title' => $this->faker->words(3, true),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'image' => null,
            'meta' => null,
            'sort_order' => 0,
            'is_active' => true,
            'allow_recurring' => false,
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn () => ['allow_recurring' => true]);
    }
}
