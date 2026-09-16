<?php

namespace App\Support;

use InvalidArgumentException;

class SubscriptionFrequency
{
    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    public const QUARTERLY = 'quarterly';

    public const YEARLY = 'yearly';

    /**
     * Frequencies campaigns can use in admin + public checkout.
     *
     * @return list<string>
     */
    public static function campaignOptions(): array
    {
        return [
            self::MONTHLY,
            self::WEEKLY,
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::WEEKLY,
            self::MONTHLY,
            self::QUARTERLY,
            self::YEARLY,
        ];
    }

    public static function label(string $frequency): string
    {
        return match ($frequency) {
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::YEARLY => 'Yearly',
            default => throw new InvalidArgumentException("Unsupported subscription frequency: {$frequency}"),
        };
    }

    public static function adjective(string $frequency): string
    {
        return match ($frequency) {
            self::WEEKLY => 'weekly',
            self::MONTHLY => 'monthly',
            self::QUARTERLY => 'quarterly',
            self::YEARLY => 'yearly',
            default => throw new InvalidArgumentException("Unsupported subscription frequency: {$frequency}"),
        };
    }

    public static function giftLabel(string $frequency): string
    {
        return match ($frequency) {
            self::WEEKLY => 'Weekly gift',
            self::MONTHLY => 'Monthly gift',
            self::QUARTERLY => 'Quarterly gift',
            self::YEARLY => 'Yearly gift',
            default => throw new InvalidArgumentException("Unsupported subscription frequency: {$frequency}"),
        };
    }

    public static function periodLabel(string $frequency): string
    {
        return match ($frequency) {
            self::WEEKLY => 'per week',
            self::MONTHLY => 'per month',
            self::QUARTERLY => 'per quarter',
            self::YEARLY => 'per year',
            default => throw new InvalidArgumentException("Unsupported subscription frequency: {$frequency}"),
        };
    }

    public static function ctaLabel(string $frequency): string
    {
        return match ($frequency) {
            self::WEEKLY => 'Start Weekly Donation',
            self::MONTHLY => 'Start Monthly Donation',
            self::QUARTERLY => 'Start Quarterly Donation',
            self::YEARLY => 'Start Yearly Donation',
            default => throw new InvalidArgumentException("Unsupported subscription frequency: {$frequency}"),
        };
    }

    public static function cadenceDescription(string $frequency): string
    {
        return match ($frequency) {
            self::WEEKLY => 'Your campaign amount will be charged every week until you cancel the mandate.',
            self::MONTHLY => 'Your campaign amount will be charged every month until you cancel the mandate.',
            self::QUARTERLY => 'Your campaign amount will be charged every quarter until you cancel the mandate.',
            self::YEARLY => 'Your campaign amount will be charged every year until you cancel the mandate.',
            default => throw new InvalidArgumentException("Unsupported subscription frequency: {$frequency}"),
        };
    }

    /**
     * @return array{period: string, interval: int}
     */
    public static function razorpayPeriod(string $frequency): array
    {
        return match ($frequency) {
            self::WEEKLY => ['period' => 'weekly', 'interval' => 1],
            self::MONTHLY => ['period' => 'monthly', 'interval' => 1],
            self::QUARTERLY => ['period' => 'monthly', 'interval' => 3],
            self::YEARLY => ['period' => 'yearly', 'interval' => 1],
            default => throw new InvalidArgumentException("Unsupported subscription frequency: {$frequency}"),
        };
    }

    public static function normalize(string $frequency): string
    {
        $normalized = strtolower(trim($frequency));

        if (! in_array($normalized, self::all(), true)) {
            throw new InvalidArgumentException("Unsupported subscription frequency: {$frequency}");
        }

        return $normalized;
    }

    public static function assertSupported(string $frequency, ?array $allowed = null): void
    {
        $frequency = self::normalize($frequency);
        $allowed ??= config('payments.razorpay.subscription_frequencies', [self::MONTHLY]);

        if (! in_array($frequency, $allowed, true)) {
            throw new InvalidArgumentException("Subscription frequency [{$frequency}] is not enabled.");
        }
    }
}
