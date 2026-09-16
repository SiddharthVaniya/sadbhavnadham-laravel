<?php

use App\Jobs\LogDonationToSheetJob;
use App\Jobs\SendDonationReceiptJob;
use App\Models\AnalyticsEvent;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\Setting;
use App\Services\DonationSubscriptionWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function subscriptionWebhookPayload(string $event, DonationSubscription $subscription, array $overrides = []): array
{
    return array_replace_recursive([
        'event' => $event,
        'payload' => [
            'subscription' => [
                'entity' => [
                    'id' => $subscription->razorpay_subscription_id,
                    'status' => 'active',
                    'paid_count' => 1,
                    'charge_at' => now()->addMonth()->timestamp,
                    'current_start' => now()->timestamp,
                ],
            ],
            'payment' => [
                'entity' => [
                    'id' => 'pay_sub_test_001',
                    'amount' => (int) round(((float) $subscription->total_amount) * 100),
                    'currency' => 'INR',
                    'subscription_id' => $subscription->razorpay_subscription_id,
                ],
            ],
        ],
    ], $overrides);
}

it('activates a subscription from subscription.activated webhook', function () {
    $subscription = DonationSubscription::factory()->create([
        'status' => DonationSubscription::STATUS_AUTHENTICATED,
        'started_at' => null,
    ]);

    app(DonationSubscriptionWebhookService::class)->handle(
        'subscription.activated',
        subscriptionWebhookPayload('subscription.activated', $subscription)
    );

    $subscription->refresh();

    expect($subscription->status)->toBe(DonationSubscription::STATUS_ACTIVE)
        ->and($subscription->started_at)->not->toBeNull()
        ->and($subscription->next_charge_at)->not->toBeNull();
});

it('creates a paid recurring donation order from subscription.charged webhook', function () {
    Bus::fake();

    Setting::updateOrCreate(['key' => Setting::SEND_RECEIPT_EMAIL], ['value' => '1']);
    Setting::updateOrCreate(['key' => Setting::SEND_WHATSAPP_THANK_YOU], ['value' => '0']);

    $subscription = DonationSubscription::factory()->create([
        'status' => DonationSubscription::STATUS_AUTHENTICATED,
        'billing_cycle_count' => 0,
        'total_amount' => 500,
        'unit_amount' => 500,
    ]);

    app(DonationSubscriptionWebhookService::class)->handle(
        'subscription.charged',
        subscriptionWebhookPayload('subscription.charged', $subscription)
    );

    $subscription->refresh();

    $order = DonationOrder::query()
        ->where('donation_subscription_id', $subscription->id)
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->is_recurring)->toBeTrue()
        ->and($order->billing_cycle_number)->toBe(1)
        ->and($order->isPaid())->toBeTrue()
        ->and($order->provider_payment_id)->toBe('pay_sub_test_001')
        ->and($order->receipt_number)->not->toBeNull()
        ->and($subscription->status)->toBe(DonationSubscription::STATUS_ACTIVE)
        ->and($subscription->billing_cycle_count)->toBe(1);

    Bus::assertDispatched(LogDonationToSheetJob::class);
    Bus::assertDispatched(SendDonationReceiptJob::class);

    expect(
        AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::TYPE_DONATION_PAID)
            ->where('donation_order_id', $order->id)
            ->exists()
    )->toBeTrue();
});

it('ignores duplicate subscription.charged webhooks for the same payment id', function () {
    Bus::fake();

    $subscription = DonationSubscription::factory()->create([
        'status' => DonationSubscription::STATUS_ACTIVE,
        'billing_cycle_count' => 0,
        'total_amount' => 500,
    ]);

    $payload = subscriptionWebhookPayload('subscription.charged', $subscription);
    $service = app(DonationSubscriptionWebhookService::class);

    $service->handle('subscription.charged', $payload);
    $service->handle('subscription.charged', $payload);

    expect(DonationOrder::query()->where('donation_subscription_id', $subscription->id)->count())->toBe(1);

    Bus::assertDispatchedTimes(LogDonationToSheetJob::class, 1);
});

it('processes payment.captured webhooks that include subscription_id', function () {
    Bus::fake();

    $subscription = DonationSubscription::factory()->create([
        'status' => DonationSubscription::STATUS_ACTIVE,
        'billing_cycle_count' => 0,
        'total_amount' => 750,
        'unit_amount' => 750,
    ]);

    app(DonationSubscriptionWebhookService::class)->handleSubscriptionPayment([
        'id' => 'pay_sub_test_002',
        'amount' => 75000,
        'currency' => 'INR',
        'subscription_id' => $subscription->razorpay_subscription_id,
    ]);

    $order = DonationOrder::query()
        ->where('provider_payment_id', 'pay_sub_test_002')
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->isPaid())->toBeTrue()
        ->and($order->donation_subscription_id)->toBe($subscription->id);
});

it('syncs cancelled subscriptions from subscription.cancelled webhook', function () {
    $subscription = DonationSubscription::factory()->active()->create();

    app(DonationSubscriptionWebhookService::class)->handle(
        'subscription.cancelled',
        subscriptionWebhookPayload('subscription.cancelled', $subscription, [
            'payload' => [
                'subscription' => [
                    'entity' => [
                        'status' => 'cancelled',
                        'ended_at' => now()->timestamp,
                    ],
                ],
            ],
        ])
    );

    $subscription->refresh();

    expect($subscription->status)->toBe(DonationSubscription::STATUS_CANCELLED)
        ->and($subscription->cancelled_at)->not->toBeNull()
        ->and($subscription->ended_at)->not->toBeNull()
        ->and($subscription->next_charge_at)->toBeNull();
});

it('syncs halted subscriptions from subscription.halted webhook', function () {
    $subscription = DonationSubscription::factory()->active()->create();

    app(DonationSubscriptionWebhookService::class)->handle(
        'subscription.halted',
        subscriptionWebhookPayload('subscription.halted', $subscription, [
            'payload' => [
                'subscription' => [
                    'entity' => [
                        'status' => 'halted',
                    ],
                ],
            ],
        ])
    );

    expect($subscription->fresh()->status)->toBe(DonationSubscription::STATUS_HALTED);
});

it('marks subscription cancelled when subscription.updated has cancel at cycle end', function () {
    $subscription = DonationSubscription::factory()->active()->create([
        'next_charge_at' => now()->addDays(10),
    ]);

    app(DonationSubscriptionWebhookService::class)->handle(
        'subscription.updated',
        subscriptionWebhookPayload('subscription.updated', $subscription, [
            'payload' => [
                'subscription' => [
                    'entity' => [
                        'status' => 'active',
                        'cancel_at_cycle_end' => true,
                        'current_end' => now()->addDays(10)->timestamp,
                    ],
                ],
            ],
        ])
    );

    $subscription->refresh();

    expect($subscription->status)->toBe(DonationSubscription::STATUS_CANCELLED)
        ->and($subscription->cancelled_at)->not->toBeNull()
        ->and($subscription->next_charge_at)->toBeNull();
});
