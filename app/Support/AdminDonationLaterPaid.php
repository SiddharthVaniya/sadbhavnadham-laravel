<?php

namespace App\Support;

use App\Models\DonationOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AdminDonationLaterPaid
{
    public const WINDOW_HOURS = 24;

    /**
     * @var array<int, array{uuid: string, payment_id: string, url: string}|null>
     */
    private static array $cacheByOrderId = [];

    public static function clearCache(): void
    {
        self::$cacheByOrderId = [];
    }

    /**
     * @return array{uuid: string, payment_id: string, url: string}|null
     */
    public static function summaryFor(DonationOrder $order): ?array
    {
        if (! $order->isFailed()) {
            return null;
        }

        if (! array_key_exists($order->id, self::$cacheByOrderId)) {
            self::warm(collect([$order]));
        }

        return self::$cacheByOrderId[$order->id] ?? null;
    }

    /**
     * @param  Collection<int, DonationOrder>|iterable<DonationOrder>  $orders
     */
    public static function warm(iterable $orders): void
    {
        $failed = collect($orders)
            ->filter(fn ($order) => $order instanceof DonationOrder && $order->isFailed())
            ->values();

        if ($failed->isEmpty()) {
            return;
        }

        $pending = $failed->filter(function (DonationOrder $order): bool {
            if (array_key_exists($order->id, self::$cacheByOrderId)) {
                return false;
            }

            if (! self::hasMatchableIdentity($order) || self::failureMoment($order) === null) {
                self::$cacheByOrderId[$order->id] = null;

                return false;
            }

            return true;
        })->values();

        if ($pending->isEmpty()) {
            return;
        }

        $windowStart = $pending
            ->map(fn (DonationOrder $order) => self::failureMoment($order))
            ->filter()
            ->min();
        $windowEnd = $pending
            ->map(fn (DonationOrder $order) => self::failureMoment($order)?->copy()->addHours(self::WINDOW_HOURS))
            ->filter()
            ->max();

        if ($windowStart === null || $windowEnd === null) {
            foreach ($pending as $order) {
                self::$cacheByOrderId[$order->id] = null;
            }

            return;
        }

        $donorIds = $pending->pluck('donor_id')->filter()->unique()->values()->all();
        $phones = $pending
            ->map(fn (DonationOrder $order) => self::normalizedPhone($order->donor_phone))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $emails = $pending
            ->map(fn (DonationOrder $order) => self::normalizedEmail($order->donor_email))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $paidCandidates = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->whereNotIn('id', $pending->pluck('id'))
            ->where(function ($q) use ($windowStart, $windowEnd): void {
                $q->where(function ($paidAtQuery) use ($windowStart, $windowEnd): void {
                    $paidAtQuery->whereNotNull('paid_at')
                        ->where('paid_at', '>', $windowStart)
                        ->where('paid_at', '<=', $windowEnd);
                })->orWhere(function ($createdQuery) use ($windowStart, $windowEnd): void {
                    $createdQuery->whereNull('paid_at')
                        ->where('created_at', '>', $windowStart)
                        ->where('created_at', '<=', $windowEnd);
                });
            })
            ->where(function ($q) use ($donorIds, $phones, $emails): void {
                $hasConstraint = false;

                if ($donorIds !== []) {
                    $q->whereIn('donor_id', $donorIds);
                    $hasConstraint = true;
                }

                foreach ($phones as $phone) {
                    $method = $hasConstraint ? 'orWhere' : 'where';
                    $q->{$method}(function ($phoneQuery) use ($phone): void {
                        $phoneQuery->where('donor_phone', $phone)
                            ->orWhere('donor_phone', 'like', '%'.$phone);
                    });
                    $hasConstraint = true;
                }

                if ($emails !== []) {
                    $method = $hasConstraint ? 'orWhere' : 'where';
                    $q->{$method}(function ($emailQuery) use ($emails): void {
                        foreach ($emails as $index => $email) {
                            $emailMethod = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                            $emailQuery->{$emailMethod}('LOWER(donor_email) = ?', [$email]);
                        }
                    });
                    $hasConstraint = true;
                }

                if (! $hasConstraint) {
                    $q->whereRaw('0 = 1');
                }
            })
            ->get([
                'id',
                'order_uuid',
                'donor_id',
                'donor_phone',
                'donor_email',
                'provider_payment_id',
                'provider_order_id',
                'paid_at',
                'created_at',
                'status',
            ]);

        foreach ($pending as $failed) {
            $match = $paidCandidates
                ->filter(fn (DonationOrder $paid) => self::isLaterPaidMatch($failed, $paid))
                ->sortBy([
                    fn (DonationOrder $paid) => ($paid->paid_at ?? $paid->created_at)?->getTimestamp() ?? 0,
                    fn (DonationOrder $paid) => $paid->id,
                ])
                ->first();

            self::$cacheByOrderId[$failed->id] = $match
                ? [
                    'uuid' => $match->order_uuid,
                    'payment_id' => $match->provider_payment_id ?: $match->provider_order_id ?: (string) $match->id,
                    'url' => route('admin.donations.show', $match),
                ]
                : null;
        }
    }

    public static function isLaterPaidMatch(DonationOrder $failed, DonationOrder $paid): bool
    {
        if (! $failed->isFailed() || ! $paid->isPaid() || $failed->id === $paid->id) {
            return false;
        }

        if (! self::sameDonor($failed, $paid)) {
            return false;
        }

        $failedAt = self::failureMoment($failed);
        $paidAt = $paid->paid_at ?? $paid->created_at;

        if ($failedAt === null || $paidAt === null) {
            return false;
        }

        return $paidAt->greaterThan($failedAt)
            && $paidAt->lessThanOrEqualTo($failedAt->copy()->addHours(self::WINDOW_HOURS));
    }

    public static function sameDonor(DonationOrder $a, DonationOrder $b): bool
    {
        if (filled($a->donor_id) && filled($b->donor_id)) {
            return (int) $a->donor_id === (int) $b->donor_id;
        }

        $phoneA = self::normalizedPhone($a->donor_phone);
        $phoneB = self::normalizedPhone($b->donor_phone);

        if ($phoneA !== null && $phoneB !== null) {
            return $phoneA === $phoneB;
        }

        $emailA = self::normalizedEmail($a->donor_email);
        $emailB = self::normalizedEmail($b->donor_email);

        return $emailA !== null && $emailB !== null && $emailA === $emailB;
    }

    public static function hasMatchableIdentity(DonationOrder $order): bool
    {
        return filled($order->donor_id)
            || self::normalizedPhone($order->donor_phone) !== null
            || self::normalizedEmail($order->donor_email) !== null;
    }

    public static function failureMoment(DonationOrder $order): ?CarbonInterface
    {
        return $order->failed_at ?? $order->created_at;
    }

    public static function normalizedPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return strlen($digits) >= 8 ? $digits : null;
    }

    public static function normalizedEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
