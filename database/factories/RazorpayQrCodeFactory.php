<?php

namespace Database\Factories;

use App\Models\RazorpayQrCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RazorpayQrCode>
 */
class RazorpayQrCodeFactory extends Factory
{
    protected $model = RazorpayQrCode::class;

    public function definition(): array
    {
        return [
            'razorpay_qr_code_id' => 'qr_'.fake()->regexify('[A-Za-z0-9]{14}'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'type' => RazorpayQrCode::TYPE_UPI,
            'usage' => RazorpayQrCode::USAGE_MULTIPLE,
            'fixed_amount' => false,
            'payment_amount_paise' => null,
            'status' => RazorpayQrCode::STATUS_ACTIVE,
            'image_url' => 'https://rzp.io/i/'.fake()->regexify('[A-Za-z0-9]{8}'),
            'payments_count_received' => 0,
            'payments_amount_received_paise' => 0,
            'razorpay_created_at' => now()->subDay(),
            'created_by' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => RazorpayQrCode::STATUS_CLOSED,
            'closed_at' => now()->subHour(),
            'close_reason' => 'on_demand',
        ]);
    }

    public function fixedAmount(float $rupees): static
    {
        return $this->state(fn () => [
            'fixed_amount' => true,
            'payment_amount_paise' => (int) round($rupees * 100),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'created_by' => $user->id,
        ]);
    }
}
