<?php

namespace App\Policies;

use App\Models\DonationOrder;
use App\Models\User;
use App\Support\DonationVisibility;

class DonationOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view donations')
            || $user->can('manage donations')
            || $user->can('manage receipts');
    }

    public function view(User $user, DonationOrder $donationOrder): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return DonationVisibility::canView($user, $donationOrder);
    }

    public function create(User $user): bool
    {
        return $user->can('manage donations');
    }

    public function update(User $user, DonationOrder $donationOrder): bool
    {
        if (! $user->can('manage donations')) {
            return false;
        }

        return DonationVisibility::canView($user, $donationOrder);
    }

    public function delete(User $user, DonationOrder $donationOrder): bool
    {
        return false;
    }

    public function restore(User $user, DonationOrder $donationOrder): bool
    {
        return false;
    }

    public function forceDelete(User $user, DonationOrder $donationOrder): bool
    {
        return false;
    }
}
