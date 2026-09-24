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
        foreach (collect($orders) as $order) {
            if (! $order instanceof DonationOrder || ! $order->isFailed()) {
                continue;
            }

            if (array_key_exists($order->id, self::$cacheByOrderId)) {
                continue;
            }

            self::$cacheByOrderId[$order->id] = self::resolveOne($order);
        }
    }

    /**
     * @return array{uuid: string, payment_id: string, url: string}|null
     */
    private static function resolveOne(DonationOrder $failed): ?array
    {
        if (! self::hasMatchableIdentity($failed)) {
            return null;
        }

        $failedAt = self::failureMoment($failed);

        if ($failedAt === null) {
            return null;
        }

        $windowEnd = $failedAt->copy()->addHours(self::WINDOW_HOURS);

        $query = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->where('id', '!=', $failed->id)
            ->where(function ($q) use ($failedAt, $windowEnd): void {
                $q->where(function ($paidAtQuery) use ($failedAt, $windowEnd): void {
                    $paidAtQuery->whereNotNull('paid_at')
                        ->where('paid_at', '>', $failedAt)
                        ->where('paid_at', '<=', $windowEnd);
                })->orWhere(function ($createdQuery) use ($failedAt, $windowEnd): void {
                    $createdQuery->whereNull('paid_at')
                        ->where('created_at', '>', $failedAt)
                        ->where('created_at', '<=', $windowEnd);
                });
            });

        self::applyDonorConstraint($query, $failed);

        $match = $query
            ->orderByRaw('COALESCE(paid_at, created_at) asc')
            ->orderBy('id')
            ->first(['id', 'order_uuid', 'donor_id', 'donor_phone', 'donor_email', 'provider_payment_id', 'provider_order_id', 'paid_at', 'created_at', 'status']);

        if (! $match || ! self::isLaterPaidMatch($failed, $match)) {
            return null;
        }

        return [
            'uuid' => $match->order_uuid,
            'payment_id' => $match->provider_payment_id ?: $match->provider_order_id ?: (string) $match->id,
            'url' => route('admin.donations.show', $match),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<DonationOrder>  $query
     */
    private static function applyDonorConstraint($query, DonationOrder $failed): void
    {
        if (filled($failed->donor_id)) {
            $query->where('donor_id', $failed->donor_id);

            return;
        }

        $phone = self::normalizedPhone($failed->donor_phone);

        if ($phone !== null) {
            $raw = trim((string) $failed->donor_phone);
            $query->where(function ($q) use ($phone, $raw): void {
                $q->where('donor_phone', $phone);

                if ($raw !== '' && $raw !== $phone) {
                    $q->orWhere('donor_phone', $raw);
                }

                $q->orWhere('donor_phone', 'like', '%'.$phone);
            });

            return;
        }

        $email = self::normalizedEmail($failed->donor_email);

        if ($email !== null) {
            $query->whereRaw('LOWER(donor_email) = ?', [$email]);
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
