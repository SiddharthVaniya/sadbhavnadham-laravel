<?php

namespace App\Support;

use App\Models\User;

class MarketerPortal
{
    public const ROLE = 'digital_marketer';

    /**
     * Staff roles that still use the main admin panel.
     *
     * @var list<string>
     */
    public const ADMIN_STAFF_ROLES = [
        'super_admin',
        'admin',
        'manager',
        'accountant',
        'user',
    ];

    public static function isMarketer(User $user): bool
    {
        return $user->hasRole(self::ROLE);
    }

    public static function isMarketerOnly(User $user): bool
    {
        return self::isMarketer($user) && ! $user->hasAnyRole(self::ADMIN_STAFF_ROLES);
    }

    public static function canAccessAdmin(User $user): bool
    {
        if (self::isMarketerOnly($user)) {
            return false;
        }

        return AdminPermissions::userCanAny($user, AdminPermissions::portalPermissions());
    }

    public static function homeRouteName(User $user): string
    {
        if (self::isMarketerOnly($user) || (self::isMarketer($user) && ! self::canAccessAdmin($user))) {
            return 'marketer.dashboard';
        }

        return 'admin.dashboard';
    }
}
