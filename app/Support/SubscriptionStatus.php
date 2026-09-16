<?php

namespace App\Support;

use App\Models\DonationSubscription;

class SubscriptionStatus
{
    /**
     * @return list<string>
     */
    public static function liveStatuses(): array
    {
        return [
            DonationSubscription::STATUS_ACTIVE,
            DonationSubscription::STATUS_AUTHENTICATED,
            DonationSubscription::STATUS_PENDING,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            DonationSubscription::STATUS_CREATED => 'Created',
            DonationSubscription::STATUS_AUTHENTICATED => 'Authenticated',
            DonationSubscription::STATUS_ACTIVE => 'Active',
            DonationSubscription::STATUS_PENDING => 'Pending',
            DonationSubscription::STATUS_HALTED => 'Halted',
            DonationSubscription::STATUS_CANCELLED => 'Cancelled',
            DonationSubscription::STATUS_COMPLETED => 'Completed',
        ];
    }

    public static function label(?string $status): string
    {
        if (! $status) {
            return 'Unknown';
        }

        return self::labels()[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
