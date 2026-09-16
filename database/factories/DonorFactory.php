<?php

namespace Database\Factories;

use App\Models\Donor;
use Illuminate\Database\Eloquent\Factories\Factory;

class DonorFactory extends Factory
{
    protected $model = Donor::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '9'.(string) fake()->numerify('#########'),
            'date_of_birth' => fake()->optional()->date(),
            'pan_number' => fake()->optional()->regexify('[A-Z]{5}[0-9]{4}[A-Z]{1}'),
            'address' => fake()->optional()->address(),
            'pincode' => fake()->optional()->numerify('######'),
            'city' => fake()->optional()->city(),
            'state' => fake()->optional()->state(),
            'country' => 'INDIA',
            'country_code' => 'IN',
            'consent_indian_citizen' => true,
            'last_donated_at' => now(),
        ];
    }
}
