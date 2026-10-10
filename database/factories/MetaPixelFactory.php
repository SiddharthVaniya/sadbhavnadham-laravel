<?php

namespace Database\Factories;

use App\Models\MetaPixel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetaPixel>
 */
class MetaPixelFactory extends Factory
{
    protected $model = MetaPixel::class;

    public function definition(): array
    {
        return [
            'label' => fake()->words(2, true),
            'pixel_id' => (string) fake()->numerify('##############'),
            'access_token' => 'test-token-'.fake()->uuid(),
            'is_active' => true,
            'send_purchase' => true,
            'send_initiate_checkout' => true,
            'test_event_code' => null,
        ];
    }
}
