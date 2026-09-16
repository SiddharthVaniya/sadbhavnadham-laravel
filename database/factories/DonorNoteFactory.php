<?php

namespace Database\Factories;

use App\Models\Donor;
use App\Models\DonorNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DonorNote>
 */
class DonorNoteFactory extends Factory
{
    protected $model = DonorNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'donor_id' => Donor::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(12),
        ];
    }
}
