<?php

namespace Tests\Unit;

use App\Support\SubscriptionFrequency;
use InvalidArgumentException;
use Tests\TestCase;

class SubscriptionFrequencyTest extends TestCase
{
    public function test_weekly_maps_to_razorpay_weekly_interval_one(): void
    {
        $this->assertSame(
            ['period' => 'weekly', 'interval' => 1],
            SubscriptionFrequency::razorpayPeriod(SubscriptionFrequency::WEEKLY)
        );
    }

    public function test_monthly_maps_to_razorpay_monthly_interval_one(): void
    {
        $this->assertSame(
            ['period' => 'monthly', 'interval' => 1],
            SubscriptionFrequency::razorpayPeriod(SubscriptionFrequency::MONTHLY)
        );
    }

    public function test_quarterly_maps_to_razorpay_monthly_interval_three(): void
    {
        $this->assertSame(
            ['period' => 'monthly', 'interval' => 3],
            SubscriptionFrequency::razorpayPeriod(SubscriptionFrequency::QUARTERLY)
        );
    }

    public function test_yearly_maps_to_razorpay_yearly_interval_one(): void
    {
        $this->assertSame(
            ['period' => 'yearly', 'interval' => 1],
            SubscriptionFrequency::razorpayPeriod(SubscriptionFrequency::YEARLY)
        );
    }

    public function test_assert_supported_rejects_disabled_frequency(): void
    {
        config(['payments.razorpay.subscription_frequencies' => [SubscriptionFrequency::MONTHLY]]);

        $this->expectException(InvalidArgumentException::class);

        SubscriptionFrequency::assertSupported(SubscriptionFrequency::YEARLY);
    }
}
