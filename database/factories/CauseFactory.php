<?php

namespace Database\Factories;

use App\Models\Cause;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CauseFactory extends Factory
{
    protected $model = Cause::class;

    public function definition(): array
    {
        $title = $this->faker->words(2, true);

        return [
            'slug' => Str::slug($title).'-'.$this->faker->unique()->randomNumber(),
            'icon_uri' => null,
            'icon_uri_active' => null,
            'title' => $title,
            'excerpt' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'images' => [],
            'hero_image' => null,
            'details' => [],
            'allow_custom_amount' => true,
            'allow_recurring' => false,
            'allow_weekly_recurring' => false,
            'pan_required' => true,
            'default_amount' => $this->faker->randomFloat(2, 100, 5000),
            'default_title' => 'General Donation',
            'cta_text' => 'Donate',
            'sort_order' => 0,
            'is_active' => true,
            'aisensy_account_id' => null,
            'aisensy_payment_link_campaign' => null,
            'aisensy_thank_you_campaign' => null,
            'aisensy_certificate_campaign' => null,
            'certificate_template' => null,
            'aisensy_send_thank_you' => true,
            'aisensy_send_certificate' => true,
            'aisensy_thank_you_image' => null,
            'aisensy_thank_you_message_mode' => 'template',
            'aisensy_thank_you_message_template' => null,
            'aisensy_thank_you_include_name' => true,
            'aisensy_thank_you_include_amount' => true,
            'aisensy_thank_you_include_cause' => true,
            'aisensy_thank_you_include_receipt' => false,
        ];
    }
}
