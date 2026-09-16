<?php

namespace App\Support;

use App\Models\DonationOrder;
use App\Models\Setting;

class DonationNotificationRetry
{
    public const CHANNEL_RECEIPT = 'receipt';

    public const CHANNEL_SHEET = 'sheet';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_CERTIFICATE = 'certificate';

    public const CHANNEL_RECEIPT_WHATSAPP = 'receipt_whatsapp';

    public static function retryWindowHours(): int
    {
        return Setting::getIntValue(
            Setting::RECONCILE_NOTIFICATION_RETRY_HOURS,
            max(1, (int) config('donation.reconcile_notification_retry_hours', 48)),
        );
    }

    public static function maxReconcileAttempts(): int
    {
        return Setting::getIntValue(
            Setting::RECONCILE_NOTIFICATION_MAX_ATTEMPTS,
            max(1, (int) config('donation.reconcile_notification_max_attempts', 5)),
        );
    }

    public static function jobTries(): int
    {
        return Setting::getIntValue(
            Setting::NOTIFICATION_JOB_TRIES,
            max(1, (int) config('donation.notification_job_tries', 3)),
        );
    }

    public static function jobBackoffSeconds(): int
    {
        return Setting::getIntValue(
            Setting::NOTIFICATION_JOB_BACKOFF_SECONDS,
            max(1, (int) config('donation.notification_job_backoff_seconds', 60)),
        );
    }

    public static function attemptColumn(string $channel): string
    {
        return match ($channel) {
            self::CHANNEL_RECEIPT => 'receipt_notify_attempts',
            self::CHANNEL_SHEET => 'sheet_notify_attempts',
            self::CHANNEL_WHATSAPP => 'whatsapp_notify_attempts',
            self::CHANNEL_CERTIFICATE => 'certificate_whatsapp_notify_attempts',
            self::CHANNEL_RECEIPT_WHATSAPP => 'receipt_whatsapp_notify_attempts',
            default => throw new \InvalidArgumentException('Unknown notification channel: '.$channel),
        };
    }

    public static function canReconcileRetry(DonationOrder $order, string $channel): bool
    {
        $column = self::attemptColumn($channel);

        return (int) ($order->{$column} ?? 0) < self::maxReconcileAttempts();
    }

    public static function markReconcileQueued(DonationOrder $order, string $channel): void
    {
        $column = self::attemptColumn($channel);

        $order->increment($column);
        $order->refresh();
    }

    public static function markExhausted(DonationOrder $order, string $channel, string $reason): void
    {
        $column = self::attemptColumn($channel);
        $max = self::maxReconcileAttempts();

        $payload = [
            $column => $max,
        ];

        if ($channel === self::CHANNEL_RECEIPT) {
            $payload['receipt_failed_at'] = now();
            $payload['receipt_last_error'] = $reason;
        }

        if ($channel === self::CHANNEL_WHATSAPP) {
            $payload['whatsapp_failed_at'] = now();
            $payload['whatsapp_last_error'] = $reason;
        }

        if ($channel === self::CHANNEL_CERTIFICATE) {
            $payload['certificate_whatsapp_failed_at'] = now();
            $payload['certificate_whatsapp_last_error'] = $reason;
        }

        if ($channel === self::CHANNEL_RECEIPT_WHATSAPP) {
            $payload['receipt_whatsapp_failed_at'] = now();
            $payload['receipt_whatsapp_last_error'] = $reason;
        }

        $order->forceFill($payload)->save();
    }
}
