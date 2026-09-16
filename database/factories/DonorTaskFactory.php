<?php

namespace Database\Factories;

use App\Models\Donor;
use App\Models\DonorTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DonorTask>
 */
class DonorTaskFactory extends Factory
{
    protected $model = DonorTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'donor_id' => Donor::factory(),
            'assigned_to' => User::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => fake()->optional()->sentence(10),
            'status' => DonorTask::STATUS_OPEN,
            'due_at' => now()->addDays(3),
            'completed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => DonorTask::STATUS_OPEN,
            'completed_at' => null,
        ]);
    }

    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => DonorTask::STATUS_DONE,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => DonorTask::STATUS_CANCELLED,
            'completed_at' => null,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => DonorTask::STATUS_OPEN,
            'due_at' => now()->subDay(),
            'completed_at' => null,
        ]);
    }
}
