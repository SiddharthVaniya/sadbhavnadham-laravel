<?php

namespace App\Services;

use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Support\Attribution\AttributionParameters;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DonationSubscriptionWebhookService
{
    public function __construct(
        private DonationPaymentService $donationPaymentService,
        private RazorpaySubscriptionService $razorpaySubscriptionService,
    ) {}

    public function handle(string $event, array $payload): void
    {
        match ($event) {
            'subscription.authenticated' => $this->syncSubscriptionFromPayload(
                $payload,
                DonationSubscription::STATUS_AUTHENTICATED
            ),
            'subscription.activated' => $this->handleActivated($payload),
            'subscription.charged' => $this->handleCharged($payload),
            'subscription.pending' => $this->syncSubscriptionFromPayload(
                $payload,
                DonationSubscription::STATUS_PENDING
            ),
            'subscription.halted' => $this->syncSubscriptionFromPayload(
                $payload,
                DonationSubscription::STATUS_HALTED
            ),
            'subscription.updated' => $this->handleUpdated($payload),
            'subscription.cancelled' => $this->handleCancelled($payload),
            'subscription.completed' => $this->handleCompleted($payload),
            default => Log::info('Unhandled subscription webhook event', ['event' => $event]),
        };
    }

    public function handleSubscriptionPayment(array $payment): void
    {
        $this->processChargePayment($payment);
    }

    public function handleSubscriptionPaymentFailed(array $payment): void
    {
        $razorpaySubscriptionId = $payment['subscription_id'] ?? null;

        if (! $razorpaySubscriptionId) {
            return;
        }

        $subscription = DonationSubscription::query()
            ->where('razorpay_subscription_id', $razorpaySubscriptionId)
            ->first();

        if (! $subscription) {
            Log::warning('Subscription payment failed webhook without local subscription', [
                'razorpay_subscription_id' => $razorpaySubscriptionId,
                'payment_id' => $payment['id'] ?? null,
            ]);

            return;
        }

        Log::info('Recurring donation payment failed', [
            'donation_subscription_id' => $subscription->id,
            'razorpay_subscription_id' => $razorpaySubscriptionId,
            'payment_id' => $payment['id'] ?? null,
            'error_code' => $payment['error_code'] ?? null,
            'error_description' => $payment['error_description'] ?? null,
        ]);
    }

    private function handleActivated(array $payload): void
    {
        $entity = $this->subscriptionEntity($payload);

        if (! $entity) {
            return;
        }

        $this->syncSubscription($entity, DonationSubscription::STATUS_ACTIVE, [
            'started_at' => $this->timestamp($entity['current_start'] ?? $entity['start_at'] ?? null) ?? now(),
            'next_charge_at' => $this->timestamp($entity['charge_at'] ?? null),
        ]);
    }

    private function handleCharged(array $payload): void
    {
        $payment = $payload['payload']['payment']['entity'] ?? null;

        if (! is_array($payment)) {
            Log::warning('subscription.charged webhook missing payment entity', $payload);

            return;
        }

        $this->processChargePayment($payment, $this->subscriptionEntity($payload));
    }

    private function handleCancelled(array $payload): void
    {
        $entity = $this->subscriptionEntity($payload);

        if (! $entity) {
            return;
        }

        $this->syncSubscription($entity, DonationSubscription::STATUS_CANCELLED, [
            'cancelled_at' => now(),
            'ended_at' => $this->timestamp($entity['ended_at'] ?? null) ?? now(),
            'next_charge_at' => null,
        ]);
    }

    private function handleCompleted(array $payload): void
    {
        $entity = $this->subscriptionEntity($payload);

        if (! $entity) {
            return;
        }

        $this->syncSubscription($entity, DonationSubscription::STATUS_COMPLETED, [
            'ended_at' => $this->timestamp($entity['ended_at'] ?? null) ?? now(),
            'next_charge_at' => null,
        ]);
    }

    private function handleUpdated(array $payload): void
    {
        $entity = $this->subscriptionEntity($payload);

        if (! $entity) {
            return;
        }

        $this->syncSubscription(
            $entity,
            $this->razorpaySubscriptionService->mapRazorpayStatus($entity['status'] ?? null)
        );
    }

    private function syncSubscriptionFromPayload(array $payload, string $status): void
    {
        $entity = $this->subscriptionEntity($payload);

        if (! $entity) {
            return;
        }

        $this->syncSubscription($entity, $status);
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $extra
     */
    private function syncSubscription(array $entity, string $status, array $extra = []): void
    {
        $subscription = $this->findSubscription($entity['id'] ?? null);

        if (! $subscription) {
            return;
        }

        if ($subscription->isCancelled() && $status === DonationSubscription::STATUS_ACTIVE) {
            Log::info('Ignoring webhook that would re-activate a cancelled subscription', [
                'donation_subscription_id' => $subscription->id,
                'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
                'entity_status' => $entity['status'] ?? null,
            ]);

            return;
        }

        $updates = $this->razorpaySubscriptionService->attributesFromRazorpayEntity($subscription, $entity);

        if (in_array($status, [
            DonationSubscription::STATUS_CANCELLED,
            DonationSubscription::STATUS_COMPLETED,
            DonationSubscription::STATUS_HALTED,
        ], true)) {
            $updates['status'] = $status;
        }

        $updates = array_merge($updates, $extra);

        $subscription->update($updates);

        Log::info('Donation subscription synced from webhook', [
            'donation_subscription_id' => $subscription->id,
            'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
            'status' => $subscription->status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>|null  $subscriptionEntity
     */
    private function processChargePayment(array $payment, ?array $subscriptionEntity = null): void
    {
        $paymentId = $payment['id'] ?? null;
        $razorpaySubscriptionId = $payment['subscription_id']
            ?? $subscriptionEntity['id']
            ?? null;

        if (! $paymentId || ! $razorpaySubscriptionId) {
            Log::warning('Subscription charge webhook missing payment or subscription id', [
                'payment_id' => $paymentId,
                'razorpay_subscription_id' => $razorpaySubscriptionId,
            ]);

            return;
        }

        $existing = DonationOrder::query()
            ->where('provider_payment_id', $paymentId)
            ->first();

        if ($existing?->isPaid()) {
            return;
        }

        $order = DB::transaction(function () use ($payment, $subscriptionEntity, $paymentId, $razorpaySubscriptionId) {
            $subscription = DonationSubscription::query()
                ->where('razorpay_subscription_id', $razorpaySubscriptionId)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                Log::error('DonationSubscription not found for charge webhook', [
                    'razorpay_subscription_id' => $razorpaySubscriptionId,
                    'payment_id' => $paymentId,
                ]);

                return null;
            }

            if ($subscription->isCancelled()) {
                Log::info('Ignoring charge webhook for cancelled subscription', [
                    'donation_subscription_id' => $subscription->id,
                    'payment_id' => $paymentId,
                ]);

                return null;
            }

            $existing = DonationOrder::query()
                ->where('provider_payment_id', $paymentId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing->isPaid() ? null : $existing;
            }

            $billingCycleNumber = $this->resolveBillingCycleNumber(
                $subscription,
                $subscriptionEntity,
                $payment
            );

            $capturedAt = DonationPaymentService::resolvePaymentCapturedAt($payment);

            $order = $this->createRecurringOrder($subscription, $payment, $billingCycleNumber, $capturedAt);

            $entity = $subscriptionEntity ?? [
                'id' => $razorpaySubscriptionId,
                'status' => 'active',
            ];

            $this->syncSubscription(
                $entity,
                DonationSubscription::STATUS_ACTIVE,
                [
                    'billing_cycle_count' => $billingCycleNumber,
                    'started_at' => $subscription->started_at ?? $capturedAt,
                    'next_charge_at' => $this->timestamp($entity['charge_at'] ?? null),
                ]
            );

            return $order;
        });

        if (! $order) {
            return;
        }

        $this->donationPaymentService->captureOrderPayment(
            $order,
            $paymentId,
            DonationPaymentService::resolvePaymentCapturedAt($payment)
        );
    }

    private function createRecurringOrder(
        DonationSubscription $subscription,
        array $payment,
        int $billingCycleNumber,
        ?Carbon $capturedAt = null,
    ): DonationOrder {
        $subscription->loadMissing(['cause', 'package']);

        $amount = isset($payment['amount'])
            ? ((int) $payment['amount']) / 100
            : (float) $subscription->total_amount;

        $capturedAt ??= DonationPaymentService::resolvePaymentCapturedAt($payment);

        $order = DonationOrder::create([
            'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
            'provider_payment_id' => $payment['id'],
            'donor_id' => $subscription->donor_id,
            'donation_subscription_id' => $subscription->id,
            'billing_cycle_number' => $billingCycleNumber,
            'is_recurring' => true,
            'donor_name' => $subscription->donor_name,
            'donor_email' => $subscription->donor_email,
            'donor_phone' => $subscription->donor_phone,
            'date_of_birth' => $subscription->date_of_birth,
            'pan_number' => $subscription->pan_number,
            'address' => $subscription->address,
            'pincode' => $subscription->pincode,
            'city' => $subscription->city,
            'state' => $subscription->state,
            'country' => $subscription->country,
            'donor_country_code' => $subscription->donor_country_code,
            'consent_indian_citizen' => $subscription->consent_indian_citizen,
            'currency' => $subscription->currency,
            'total_amount' => $amount,
            'status' => DonationOrder::STATUS_PENDING,
            'created_at' => $capturedAt,
            'updated_at' => $capturedAt,

            // Every billing cycle keeps the attribution captured at signup, so partner
            // and marketing credit survive charges that happen months later.
            ...AttributionParameters::inheritFrom($subscription),
        ]);

        if (! $order->created_at?->equalTo($capturedAt)) {
            $order->forceFill([
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ])->saveQuietly();
        }

        DonationItem::create([
            'donation_order_id' => $order->id,
            'cause_id' => $subscription->cause_id,
            'cause_package_id' => $subscription->cause_package_id,
            'donation_campaign_id' => $subscription->donation_campaign_id,
            'cause' => $subscription->cause?->slug ?? '',
            'title' => $subscription->item_title,
            'quantity' => $subscription->quantity,
            'unit_amount' => $subscription->unit_amount,
            'amount' => $amount,
            'meta' => array_filter(array_merge([
                'cause_title' => $subscription->cause?->title,
                'cause_slug' => $subscription->cause?->slug,
                'subscription_uuid' => $subscription->subscription_uuid,
                'billing_cycle_number' => $billingCycleNumber,
                'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
            ], is_array($subscription->meta['campaign'] ?? null) ? ['campaign' => $subscription->meta['campaign']] : [])),
        ]);

        return $order;
    }

    /**
     * @param  array<string, mixed>|null  $subscriptionEntity
     * @param  array<string, mixed>  $payment
     */
    private function resolveBillingCycleNumber(
        DonationSubscription $subscription,
        ?array $subscriptionEntity,
        array $payment
    ): int {
        if (isset($subscriptionEntity['paid_count'])) {
            return max(1, (int) $subscriptionEntity['paid_count']);
        }

        if (isset($payment['notes']['billing_cycle_number'])) {
            return max(1, (int) $payment['notes']['billing_cycle_number']);
        }

        return max(1, (int) $subscription->billing_cycle_count + 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subscriptionEntity(array $payload): ?array
    {
        $entity = $payload['payload']['subscription']['entity'] ?? null;

        if (! is_array($entity) || empty($entity['id'])) {
            Log::warning('Razorpay subscription webhook missing subscription entity', [
                'event' => $payload['event'] ?? null,
            ]);

            return null;
        }

        return $entity;
    }

    private function findSubscription(?string $razorpaySubscriptionId): ?DonationSubscription
    {
        if (! $razorpaySubscriptionId) {
            return null;
        }

        $subscription = DonationSubscription::query()
            ->where('razorpay_subscription_id', $razorpaySubscriptionId)
            ->first();

        if (! $subscription) {
            Log::error('DonationSubscription not found for webhook', [
                'razorpay_subscription_id' => $razorpaySubscriptionId,
            ]);
        }

        return $subscription;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value)->setTimezone(config('app.timezone'));
        }

        return Carbon::parse((string) $value);
    }
}
