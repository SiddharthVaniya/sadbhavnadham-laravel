<?php

namespace App\Services;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationSubscription;
use App\Models\RazorpayPlan;
use App\Support\RazorpayDonationLabels;
use App\Support\SubscriptionFrequency;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Razorpay\Api\Api;

class RazorpaySubscriptionService
{
    public function isEnabled(): bool
    {
        return (bool) config('payments.razorpay.subscriptions_enabled', false);
    }

    public function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new InvalidArgumentException('Recurring donations are not enabled.');
        }
    }

    public function validateAmount(float $amount): void
    {
        $min = (float) config('payments.razorpay.subscription_min_amount', 100);
        $max = (float) config('payments.razorpay.subscription_max_amount', 15000);

        if ($amount < $min || $amount > $max) {
            throw new InvalidArgumentException(
                "Recurring donation amount must be between {$min} and {$max} INR."
            );
        }
    }

    public function validateFrequency(string $frequency): void
    {
        SubscriptionFrequency::assertSupported($frequency);
    }

    public function defaultTotalCount(): int
    {
        return max(1, (int) config('payments.razorpay.subscription_total_count', 360));
    }

    public function maxTotalCountForFrequency(string $frequency): int
    {
        $maxYears = max(1, (int) config('payments.razorpay.subscription_max_validity_years', 30));

        return match ($frequency) {
            SubscriptionFrequency::WEEKLY => $maxYears * 52,
            SubscriptionFrequency::MONTHLY => $maxYears * 12,
            SubscriptionFrequency::QUARTERLY => $maxYears * 4,
            SubscriptionFrequency::YEARLY => $maxYears,
            default => $maxYears * 12,
        };
    }

    public function capTotalCountForFrequency(string $frequency, int $totalCount): int
    {
        $razorpayMax = max(1, (int) config('payments.razorpay.subscription_max_total_count', 10000));

        return max(1, min($totalCount, $this->maxTotalCountForFrequency($frequency), $razorpayMax));
    }

    public function resolveTotalCount(?int $totalCount): int
    {
        return $totalCount ?? $this->defaultTotalCount();
    }

    public function defaultTotalCountForFrequency(string $frequency): int
    {
        if ($frequency === SubscriptionFrequency::WEEKLY) {
            return $this->maxTotalCountForFrequency($frequency);
        }

        return $this->defaultTotalCount();
    }

    public function resolveTotalCountForFrequency(string $frequency, ?int $totalCount = null): int
    {
        $requested = max(1, $totalCount ?? $this->defaultTotalCountForFrequency($frequency));
        $capped = $this->capTotalCountForFrequency($frequency, $requested);

        if ($capped < $requested) {
            Log::warning('Razorpay subscription total_count capped to validity limit', [
                'frequency' => $frequency,
                'requested_total_count' => $requested,
                'capped_total_count' => $capped,
                'max_validity_years' => config('payments.razorpay.subscription_max_validity_years', 30),
            ]);
        }

        return $capped;
    }

    public function resolvePlan(CausePackage $package, string $frequency): RazorpayPlan
    {
        $this->assertEnabled();
        $this->validateFrequency($frequency);
        $this->validateAmount((float) $package->amount);

        $existing = RazorpayPlan::query()
            ->where('cause_package_id', $package->id)
            ->where('frequency', $frequency)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createPlanOnRazorpay($package, $frequency);
    }

    public function resolvePlanForDonation(
        Cause $cause,
        string $frequency,
        float $amount,
        ?CausePackage $package = null,
        bool $amountLocked = false,
    ): RazorpayPlan {
        if ($package) {
            return $this->resolvePlan($package, $frequency);
        }

        return $this->resolveCustomAmountPlan($cause, $frequency, round($amount, 2), $amountLocked);
    }

    public function resolveCustomAmountPlan(
        Cause $cause,
        string $frequency,
        float $amount,
        bool $amountLocked = false,
    ): RazorpayPlan {
        $this->assertEnabled();
        $this->validateFrequency($frequency);
        $amount = round($amount, 2);
        $this->validateAmount($amount);

        if (! $cause->allow_recurring) {
            throw new InvalidArgumentException("Recurring donations are not enabled for cause [{$cause->slug}].");
        }

        if (! $cause->allow_custom_amount && ! $amountLocked) {
            throw new InvalidArgumentException('Custom recurring amounts are not enabled for this cause.');
        }

        $existing = $this->findCustomAmountPlan($cause->id, $frequency, $amount);

        if ($existing) {
            return $existing;
        }

        try {
            return $this->createCustomPlanOnRazorpay($cause, $frequency, $amount);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findCustomAmountPlan($cause->id, $frequency, $amount);

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function syncPlanForPackage(CausePackage $package, string $frequency): RazorpayPlan
    {
        return $this->resolvePlan($package->loadMissing('cause'), $frequency);
    }

    public function createPlanOnRazorpay(CausePackage $package, string $frequency): RazorpayPlan
    {
        $package->loadMissing('cause');

        $cause = $package->cause;
        if (! $cause instanceof Cause) {
            throw new InvalidArgumentException('Cause package must belong to a cause.');
        }

        if (! $cause->allow_recurring) {
            throw new InvalidArgumentException("Recurring donations are not enabled for cause [{$cause->slug}].");
        }

        if (! $package->allow_recurring) {
            throw new InvalidArgumentException("Recurring donations are not enabled for package [{$package->title}].");
        }

        $this->validateFrequency($frequency);
        $this->validateAmount((float) $package->amount);

        $period = SubscriptionFrequency::razorpayPeriod($frequency);
        $planName = $this->planName($cause, $package, $frequency);
        $amountPaise = (int) round(((float) $package->amount) * 100);

        $api = $this->api();

        $response = $this->entityToArray($api->plan->create([
            'period' => $period['period'],
            'interval' => $period['interval'],
            'item' => [
                'name' => Str::limit($planName, 250),
                'amount' => $amountPaise,
                'currency' => 'INR',
                'description' => RazorpayDonationLabels::description($cause, $package),
            ],
            'notes' => [
                'cause_id' => (string) $cause->id,
                'cause_slug' => $cause->slug,
                'cause_package_id' => (string) $package->id,
                'frequency' => $frequency,
            ],
        ]));

        try {
            return RazorpayPlan::create([
                'cause_id' => $cause->id,
                'cause_package_id' => $package->id,
                'frequency' => $frequency,
                'amount' => round((float) $package->amount, 2),
                'currency' => 'INR',
                'razorpay_plan_id' => $response['id'],
                'plan_name' => $planName,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = RazorpayPlan::query()
                ->where('cause_package_id', $package->id)
                ->where('frequency', $frequency)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function createCustomPlanOnRazorpay(Cause $cause, string $frequency, float $amount): RazorpayPlan
    {
        $this->validateFrequency($frequency);
        $amount = round($amount, 2);
        $this->validateAmount($amount);

        $period = SubscriptionFrequency::razorpayPeriod($frequency);
        $planName = $this->customPlanName($cause, $amount, $frequency);
        $amountPaise = (int) round($amount * 100);

        $response = $this->entityToArray($this->api()->plan->create([
            'period' => $period['period'],
            'interval' => $period['interval'],
            'item' => [
                'name' => Str::limit($planName, 250),
                'amount' => $amountPaise,
                'currency' => 'INR',
                'description' => RazorpayDonationLabels::description($cause, null),
            ],
            'notes' => [
                'cause_id' => (string) $cause->id,
                'cause_slug' => $cause->slug,
                'frequency' => $frequency,
                'custom_amount' => (string) $amount,
            ],
        ]));

        try {
            return RazorpayPlan::create([
                'cause_id' => $cause->id,
                'cause_package_id' => null,
                'frequency' => $frequency,
                'amount' => $amount,
                'custom_plan_key' => $cause->id.'-'.$frequency.'-'.$amount,
                'currency' => 'INR',
                'razorpay_plan_id' => $response['id'],
                'plan_name' => $planName,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findCustomAmountPlan($cause->id, $frequency, $amount);

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function findCustomAmountPlan(int $causeId, string $frequency, float $amount): ?RazorpayPlan
    {
        return RazorpayPlan::query()
            ->where('cause_id', $causeId)
            ->whereNull('cause_package_id')
            ->where('frequency', $frequency)
            ->where('amount', number_format($amount, 2, '.', ''))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createRazorpaySubscription(
        DonationSubscription $subscription,
        RazorpayPlan $plan,
        array $options = []
    ): array {
        $subscription->loadMissing(['cause', 'package']);

        $totalCount = $this->resolveTotalCountForFrequency(
            (string) $subscription->frequency,
            $subscription->total_count,
        );

        $payload = array_merge([
            'plan_id' => $plan->razorpay_plan_id,
            'total_count' => $totalCount,
            'quantity' => max(1, (int) $subscription->quantity),
            'customer_notify' => 1,
            'notes' => $this->subscriptionNotes($subscription),
        ], $options);

        $response = $this->entityToArray($this->api()->subscription->create($payload));

        $subscription->update([
            'razorpay_plan_id' => $plan->razorpay_plan_id,
            'razorpay_subscription_id' => $response['id'],
            'total_count' => $totalCount,
            'status' => $this->mapRazorpayStatus($response['status'] ?? DonationSubscription::STATUS_CREATED),
            'meta' => array_merge($subscription->meta ?? [], [
                'razorpay_subscription' => [
                    'id' => $response['id'],
                    'status' => $response['status'] ?? null,
                    'short_url' => $response['short_url'] ?? null,
                ],
            ]),
        ]);

        Log::info('Razorpay subscription created', [
            'donation_subscription_id' => $subscription->id,
            'razorpay_subscription_id' => $response['id'],
        ]);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    public function subscriptionNotes(DonationSubscription $subscription): array
    {
        $subscription->loadMissing(['cause', 'package']);

        $notes = [
            'donation_subscription_id' => (string) $subscription->id,
            'subscription_uuid' => $subscription->subscription_uuid,
            'frequency' => $subscription->frequency,
            'cause' => $subscription->cause?->slug ?? '',
            'cause_name' => Str::limit((string) ($subscription->cause?->title ?? ''), 255),
            'package_name' => $subscription->package
                ? Str::limit($subscription->package->title, 255)
                : '',
        ];

        if ($subscription->cause_package_id) {
            $notes['cause_package_id'] = (string) $subscription->cause_package_id;
        }

        return $notes;
    }

    public function mapRazorpayStatus(?string $status): string
    {
        return match ($status) {
            'created' => DonationSubscription::STATUS_CREATED,
            'authenticated' => DonationSubscription::STATUS_AUTHENTICATED,
            'active' => DonationSubscription::STATUS_ACTIVE,
            'pending' => DonationSubscription::STATUS_PENDING,
            'halted' => DonationSubscription::STATUS_HALTED,
            'cancelled' => DonationSubscription::STATUS_CANCELLED,
            'completed' => DonationSubscription::STATUS_COMPLETED,
            default => DonationSubscription::STATUS_CREATED,
        };
    }

    /**
     * Razorpay can keep status "active" until the billing cycle ends after cancellation.
     *
     * @param  array<string, mixed>  $entity
     */
    public function isScheduledCancellation(array $entity): bool
    {
        if ($this->isTruthy($entity['cancel_at_cycle_end'] ?? false)) {
            return true;
        }

        if (! $this->isTruthy($entity['has_scheduled_changes'] ?? false)) {
            return false;
        }

        $scheduledAt = (string) ($entity['change_scheduled_at'] ?? '');

        return $scheduledAt === '' || $scheduledAt === 'cycle_end';
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>
     */
    public function attributesFromRazorpayEntity(DonationSubscription $subscription, array $entity): array
    {
        $razorpayStatus = (string) ($entity['status'] ?? '');
        $status = $this->mapRazorpayStatus($razorpayStatus !== '' ? $razorpayStatus : null);
        $cancelAtCycleEnd = $this->isScheduledCancellation($entity);

        $updates = [
            'billing_cycle_count' => isset($entity['paid_count'])
                ? (int) $entity['paid_count']
                : $subscription->billing_cycle_count,
            'meta' => array_merge($subscription->meta ?? [], [
                'razorpay_sync' => [
                    'synced_at' => now()->toIso8601String(),
                    'status' => $razorpayStatus !== '' ? $razorpayStatus : null,
                    'cancel_at_cycle_end' => $cancelAtCycleEnd,
                ],
            ]),
        ];

        if ($cancelAtCycleEnd && $razorpayStatus === 'active') {
            $updates['status'] = DonationSubscription::STATUS_CANCELLED;
            $updates['cancelled_at'] = $subscription->cancelled_at ?? now();
            $updates['ended_at'] = $subscription->ended_at
                ?? $this->timestampFromRazorpay($entity['current_end'] ?? null)
                ?? $subscription->next_charge_at
                ?? now();
            $updates['next_charge_at'] = null;
            $updates['meta']['cancel_at_cycle_end'] = true;

            return $updates;
        }

        $updates['status'] = $status;
        $updates['next_charge_at'] = $this->timestampFromRazorpay($entity['charge_at'] ?? null);

        if (! $subscription->started_at && $razorpayStatus === 'active') {
            $updates['started_at'] = $this->timestampFromRazorpay($entity['current_start'] ?? null) ?? now();
        }

        if (in_array($status, [DonationSubscription::STATUS_CANCELLED, DonationSubscription::STATUS_COMPLETED], true)) {
            $updates['cancelled_at'] = $subscription->cancelled_at ?? now();
            $updates['ended_at'] = $this->timestampFromRazorpay($entity['ended_at'] ?? null) ?? now();
            $updates['next_charge_at'] = null;
        }

        return $updates;
    }

    public function planName(Cause $cause, CausePackage $package, string $frequency): string
    {
        return sprintf(
            '%s - %s (%s)',
            $cause->title,
            $package->title,
            SubscriptionFrequency::label($frequency)
        );
    }

    public function customPlanName(Cause $cause, float $amount, string $frequency): string
    {
        return sprintf(
            '%s - Custom ₹%s (%s)',
            $cause->title,
            number_format($amount, 0),
            SubscriptionFrequency::label($frequency)
        );
    }

    public function cancelSubscription(DonationSubscription $subscription, ?string $reason = null, bool $cancelAtCycleEnd = false): DonationSubscription
    {
        if (! $subscription->canBeCancelled()) {
            throw new InvalidArgumentException('This subscription cannot be cancelled.');
        }

        $razorpayResponse = null;

        if ($subscription->razorpay_subscription_id) {
            $razorpayResponse = $this->cancelOnRazorpay(
                $subscription->razorpay_subscription_id,
                $cancelAtCycleEnd
            );
        }

        $razorpayStatus = (string) ($razorpayResponse['status'] ?? '');
        $cancelAtCycleEnd = $cancelAtCycleEnd
            || (bool) ($razorpayResponse['cancel_at_cycle_end'] ?? false);

        $subscription->update([
            'status' => DonationSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'ended_at' => $cancelAtCycleEnd && $razorpayStatus === 'active'
                ? ($subscription->next_charge_at ?? now())
                : now(),
            'cancel_reason' => $reason,
            'next_charge_at' => null,
            'meta' => array_merge($subscription->meta ?? [], [
                'admin_cancelled_at' => now()->toIso8601String(),
                'cancel_at_cycle_end' => $cancelAtCycleEnd,
                'razorpay_cancel' => $razorpayResponse,
            ]),
        ]);

        Log::info('Donation subscription cancelled from admin', [
            'donation_subscription_id' => $subscription->id,
            'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
            'cancel_at_cycle_end' => $cancelAtCycleEnd,
            'razorpay_status' => $razorpayStatus ?: null,
        ]);

        return $subscription->fresh();
    }

    public function syncSubscriptionFromRazorpay(DonationSubscription $subscription): DonationSubscription
    {
        if (! $subscription->razorpay_subscription_id) {
            throw new InvalidArgumentException('This subscription is not linked to Razorpay.');
        }

        $entity = $this->fetchSubscription($subscription->razorpay_subscription_id);

        $subscription->update(
            $this->attributesFromRazorpayEntity($subscription, $entity)
        );

        return $subscription->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchSubscription(string $razorpaySubscriptionId): array
    {
        return $this->entityToArray($this->api()->subscription->fetch($razorpaySubscriptionId));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cancelOnRazorpay(string $razorpaySubscriptionId, bool $cancelAtCycleEnd): ?array
    {
        $options = $cancelAtCycleEnd ? ['cancel_at_cycle_end' => 1] : ['cancel_at_cycle_end' => 0];

        try {
            return $this->entityToArray(
                $this->api()->subscription->fetch($razorpaySubscriptionId)->cancel($options)
            );
        } catch (\Throwable $exception) {
            $entity = $this->fetchSubscription($razorpaySubscriptionId);
            $remoteStatus = (string) ($entity['status'] ?? '');

            if (in_array($remoteStatus, ['cancelled', 'completed'], true)) {
                return $entity;
            }

            throw $exception;
        }
    }

    private function timestampFromRazorpay(mixed $value): ?\Illuminate\Support\Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return \Illuminate\Support\Carbon::createFromTimestamp((int) $value)->setTimezone(config('app.timezone'));
        }

        return \Illuminate\Support\Carbon::parse((string) $value);
    }

    private function api(): Api
    {
        return new Api(
            config('payments.razorpay.key'),
            config('payments.razorpay.secret')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function entityToArray(mixed $entity): array
    {
        if (is_array($entity)) {
            return $entity;
        }

        if (is_object($entity) && method_exists($entity, 'toArray')) {
            return $entity->toArray();
        }

        return json_decode(json_encode($entity), true) ?? [];
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true'], true);
    }
}
