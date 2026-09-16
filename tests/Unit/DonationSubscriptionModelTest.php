<?php

namespace Tests\Unit;

use App\Models\DonationSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationSubscriptionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_uuid_is_generated_on_create(): void
    {
        $subscription = DonationSubscription::factory()->create([
            'subscription_uuid' => null,
        ]);

        $this->assertNotEmpty($subscription->subscription_uuid);
    }

    public function test_route_key_uses_subscription_uuid(): void
    {
        $subscription = new DonationSubscription;

        $this->assertSame('subscription_uuid', $subscription->getRouteKeyName());
    }
}
