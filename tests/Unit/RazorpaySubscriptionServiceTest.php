<?php

namespace Tests\Unit;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationSubscription;
use App\Services\RazorpaySubscriptionService;
use App\Support\SubscriptionFrequency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RazorpaySubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RazorpaySubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RazorpaySubscriptionService::class);
    }

    public function test_is_enabled_respects_config_flag(): void
    {
        config(['payments.razorpay.subscriptions_enabled' => false]);
        $this->assertFalse($this->service->isEnabled());

        config(['payments.razorpay.subscriptions_enabled' => true]);
        $this->assertTrue($this->service->isEnabled());
    }

    public function test_validate_amount_enforces_bounds(): void
    {
        config([
            'payments.razorpay.subscription_min_amount' => 100,
            'payments.razorpay.subscription_max_amount' => 15000,
        ]);

        $this->service->validateAmount(500);

        $this->expectException(InvalidArgumentException::class);
        $this->service->validateAmount(50);
    }

    public function test_validate_frequency_rejects_unsupported_value(): void
    {
        config(['payments.razorpay.subscription_frequencies' => [SubscriptionFrequency::MONTHLY]]);

        $this->service->validateFrequency(SubscriptionFrequency::MONTHLY);

        $this->expectException(InvalidArgumentException::class);
        $this->service->validateFrequency(SubscriptionFrequency::YEARLY);
    }

    public function test_map_razorpay_status(): void
    {
        $this->assertSame(
            DonationSubscription::STATUS_ACTIVE,
            $this->service->mapRazorpayStatus('active')
        );
        $this->assertSame(
            DonationSubscription::STATUS_HALTED,
            $this->service->mapRazorpayStatus('halted')
        );
    }

    public function test_attributes_from_razorpay_entity_marks_cancel_at_cycle_end_as_cancelled(): void
    {
        $subscription = DonationSubscription::factory()->create([
            'status' => DonationSubscription::STATUS_ACTIVE,
            'next_charge_at' => now()->addMonth(),
        ]);

        $attributes = $this->service->attributesFromRazorpayEntity($subscription, [
            'status' => 'active',
            'cancel_at_cycle_end' => true,
            'current_end' => now()->addMonth()->timestamp,
            'paid_count' => 1,
        ]);

        $this->assertSame(DonationSubscription::STATUS_CANCELLED, $attributes['status']);
        $this->assertNull($attributes['next_charge_at']);
        $this->assertTrue($attributes['meta']['cancel_at_cycle_end']);
    }

    public function test_plan_name_includes_cause_package_and_frequency(): void
    {
        $cause = new Cause([
            'title' => 'Old Age Home',
            'slug' => 'old-age-home',
        ]);

        $package = new CausePackage([
            'title' => 'Morning Breakfast',
        ]);

        $this->assertSame(
            'Old Age Home - Morning Breakfast (Monthly)',
            $this->service->planName($cause, $package, SubscriptionFrequency::MONTHLY)
        );
    }

    public function test_subscription_notes_include_local_ids(): void
    {
        $cause = Cause::factory()->create([
            'title' => 'Tree Plantation',
            'slug' => 'tree',
        ]);

        $package = CausePackage::factory()->for($cause)->create([
            'title' => 'One Tree',
        ]);

        $subscription = DonationSubscription::factory()->create([
            'cause_id' => $cause->id,
            'cause_package_id' => $package->id,
            'frequency' => SubscriptionFrequency::MONTHLY,
        ]);

        $notes = $this->service->subscriptionNotes($subscription);

        $this->assertSame((string) $subscription->id, $notes['donation_subscription_id']);
        $this->assertSame($subscription->subscription_uuid, $notes['subscription_uuid']);
        $this->assertSame('tree', $notes['cause']);
        $this->assertSame('One Tree', $notes['package_name']);
        $this->assertSame((string) $package->id, $notes['cause_package_id']);
    }

    public function test_can_be_cancelled_for_live_statuses_only(): void
    {
        $active = DonationSubscription::factory()->make([
            'status' => DonationSubscription::STATUS_ACTIVE,
        ]);
        $cancelled = DonationSubscription::factory()->make([
            'status' => DonationSubscription::STATUS_CANCELLED,
        ]);

        $this->assertTrue($active->canBeCancelled());
        $this->assertFalse($cancelled->canBeCancelled());
    }

    public function test_default_total_count_uses_config(): void
    {
        config(['payments.razorpay.subscription_total_count' => 240]);

        $this->assertSame(240, $this->service->defaultTotalCount());
        $this->assertSame(240, $this->service->resolveTotalCount(null));
        $this->assertSame(12, $this->service->resolveTotalCount(12));
    }

    public function test_total_count_is_capped_to_razorpay_validity_limit(): void
    {
        config([
            'payments.razorpay.subscription_total_count' => 1200,
            'payments.razorpay.subscription_max_validity_years' => 30,
        ]);

        $this->assertSame(360, $this->service->maxTotalCountForFrequency(SubscriptionFrequency::MONTHLY));
        $this->assertSame(120, $this->service->maxTotalCountForFrequency(SubscriptionFrequency::QUARTERLY));
        $this->assertSame(30, $this->service->maxTotalCountForFrequency(SubscriptionFrequency::YEARLY));
        $this->assertSame(1560, $this->service->maxTotalCountForFrequency(SubscriptionFrequency::WEEKLY));
        $this->assertSame(360, $this->service->resolveTotalCountForFrequency(SubscriptionFrequency::MONTHLY));
        $this->assertSame(360, $this->service->resolveTotalCountForFrequency(SubscriptionFrequency::MONTHLY, 1200));
        $this->assertSame(24, $this->service->resolveTotalCountForFrequency(SubscriptionFrequency::MONTHLY, 24));
        $this->assertSame(1560, $this->service->resolveTotalCountForFrequency(SubscriptionFrequency::WEEKLY));
    }
}
