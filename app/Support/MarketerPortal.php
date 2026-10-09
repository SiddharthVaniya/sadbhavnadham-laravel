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

    /**
     * Admin routes marketers may open without full admin-panel access.
     * Keys are route names; values are the permission required for view-only entry.
     *
     * @return array<string, string>
     */
    public static function allowedAdminRoutes(): array
    {
        return [
            'admin.packages.index' => AdminPermissions::PACKAGE_VIEW,
        ];
    }

    public static function canAccessAdminRoute(User $user, ?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        $requiredPermission = self::allowedAdminRoutes()[$routeName] ?? null;

        if ($requiredPermission === null) {
            return false;
        }

        return AdminPermissions::userCan($user, $requiredPermission);
    }

    public static function usesMarketerShell(User $user, ?string $routeName = null): bool
    {
        if (! self::isMarketerOnly($user)) {
            return false;
        }

        if ($routeName !== null && str_starts_with($routeName, 'marketer.')) {
            return true;
        }

        return self::canAccessAdminRoute($user, $routeName);
    }

    public static function homeRouteName(User $user): string
    {
        if (self::isMarketerOnly($user) || (self::isMarketer($user) && ! self::canAccessAdmin($user))) {
            return 'marketer.dashboard';
        }

        return 'admin.dashboard';
    }
}
