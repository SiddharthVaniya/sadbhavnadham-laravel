<?php

namespace App\Support;

use App\Models\User;

class TelecallerPortal
{
    /** Canonical slug; production DB role is often `Telecaller`. */
    public const ROLE = 'telecaller';

    /** @var list<string> */
    public const ROLE_NAMES = ['telecaller', 'Telecaller'];

    public const DEFAULT_DURATION = 'today';

    public static function isTelecaller(User $user): bool
    {
        return $user->hasAnyRole(self::ROLE_NAMES);
    }

    /**
     * Anyone with the telecaller role (except super admin) uses the failed-only donations UI.
     */
    public static function usesFailedDonationWorkflow(?User $user): bool
    {
        if (! $user || ! self::isTelecaller($user)) {
            return false;
        }

        return ! $user->hasRole(AdminRoleGuard::SUPER_ADMIN);
    }

    public static function homeRouteParameters(): array
    {
        return [
            'duration' => self::DEFAULT_DURATION,
        ];
    }

    public static function homeRouteName(): string
    {
        return 'admin.donations.telecaller';
    }
}
