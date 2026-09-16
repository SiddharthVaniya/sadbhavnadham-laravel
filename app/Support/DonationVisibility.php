<?php

namespace App\Support;

use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DonationVisibility
{
    public const VIEW_ALL = 'view all donations';

    public static function userCanViewAll(?User $user): bool
    {
        return (bool) ($user?->can(self::VIEW_ALL));
    }

    public static function canView(?User $user, DonationOrder $order): bool
    {
        if (! $user) {
            return false;
        }

        if (self::userCanViewAll($user)) {
            return true;
        }

        return (int) $order->created_by === (int) $user->id;
    }

    public static function ensureCanView(?User $user, DonationOrder $order): void
    {
        abort_unless(self::canView($user, $order), 403);
    }

    public static function apply(Builder $query, ?User $user): Builder
    {
        if (! $user || self::userCanViewAll($user)) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }
}
