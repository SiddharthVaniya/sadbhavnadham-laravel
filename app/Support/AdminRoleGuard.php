<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdminRoleGuard
{
    public const SUPER_ADMIN = 'super_admin';

    /**
     * @return list<string>
     */
    public static function protectedRoleNames(): array
    {
        return [self::SUPER_ADMIN];
    }

    public static function actorIsSuperAdmin(?User $actor): bool
    {
        return (bool) $actor?->hasRole(self::SUPER_ADMIN);
    }

    public static function superAdminCount(): int
    {
        return User::role(self::SUPER_ADMIN)->count();
    }

    /**
     * @return Collection<int, Role>
     */
    public static function assignableRoles(?User $actor): Collection
    {
        $query = Role::query()->orderBy('name');

        if (! self::actorIsSuperAdmin($actor)) {
            $query->where('name', '!=', self::SUPER_ADMIN);
        }

        return $query->get(['id', 'name']);
    }

    /**
     * @param  list<string|int>  $roleNamesOrIds
     */
    public static function assertCanAssignRoles(?User $actor, array $roleNamesOrIds): void
    {
        $resolvedNames = self::resolveRoleNames($roleNamesOrIds);

        if ($resolvedNames === []) {
            return;
        }

        if (in_array(self::SUPER_ADMIN, $resolvedNames, true) && ! self::actorIsSuperAdmin($actor)) {
            throw ValidationException::withMessages([
                'roles' => 'Only a super admin can assign the super_admin role.',
            ]);
        }
    }

    /**
     * @param  list<string|int>  $roleNamesOrIds
     * @return list<string>
     */
    public static function resolveRoleNames(array $roleNamesOrIds): array
    {
        $ids = [];
        $names = [];

        foreach ($roleNamesOrIds as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            } else {
                $names[] = (string) $value;
            }
        }

        if ($ids === [] && $names === []) {
            return [];
        }

        return Role::query()
            ->where(function ($query) use ($ids, $names): void {
                if ($ids !== []) {
                    $query->whereIn('id', $ids);
                }

                if ($names !== []) {
                    if ($ids !== []) {
                        $query->orWhereIn('name', $names);
                    } else {
                        $query->whereIn('name', $names);
                    }
                }
            })
            ->pluck('name')
            ->all();
    }

    public static function canManageUser(?User $actor, User $target): bool
    {
        if (! ($actor?->can('manage users') ?? false)) {
            return false;
        }

        if ($target->hasRole(self::SUPER_ADMIN) && ! self::actorIsSuperAdmin($actor)) {
            return false;
        }

        return true;
    }

    public static function assertCanManageUser(?User $actor, User $target): void
    {
        if (! self::canManageUser($actor, $target)) {
            abort(403, 'You are not allowed to manage this user.');
        }
    }

    public static function canDeleteUser(?User $actor, User $target): bool
    {
        if (! self::canManageUser($actor, $target)) {
            return false;
        }

        if ($target->trashed()) {
            return false;
        }

        if ($actor && $actor->id === $target->id) {
            return false;
        }

        if ($target->hasRole(self::SUPER_ADMIN)) {
            return false;
        }

        return true;
    }

    public static function assertCanDeleteUser(?User $actor, User $target): void
    {
        if (! self::canDeleteUser($actor, $target)) {
            if ($actor && $actor->id === $target->id) {
                abort(403, 'You cannot delete your own account.');
            }

            abort(403, 'You are not allowed to delete this user.');
        }
    }

    public static function canRestoreUser(?User $actor, User $target): bool
    {
        return self::canManageUser($actor, $target) && $target->trashed();
    }

    public static function assertCanRestoreUser(?User $actor, User $target): void
    {
        if (! self::canRestoreUser($actor, $target)) {
            abort(403, 'You are not allowed to restore this user.');
        }
    }

    /**
     * @param  list<string|int>  $roleNamesOrIds
     */
    public static function assertCanRemoveSuperAdminRole(User $target, array $roleNamesOrIds): void
    {
        if (! $target->hasRole(self::SUPER_ADMIN)) {
            return;
        }

        $resolvedNames = self::resolveRoleNames($roleNamesOrIds);

        if (in_array(self::SUPER_ADMIN, $resolvedNames, true)) {
            return;
        }

        if (self::superAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'roles' => 'Cannot remove the last super admin.',
            ]);
        }
    }

    public static function assertCanManageRole(?User $actor, Role $role): void
    {
        if ($role->name === self::SUPER_ADMIN && ! self::actorIsSuperAdmin($actor)) {
            abort(403, 'Only a super admin can modify the super_admin role.');
        }
    }

    public static function assertCanCreateRoleName(?User $actor, string $name): void
    {
        if ($name === self::SUPER_ADMIN && ! self::actorIsSuperAdmin($actor)) {
            throw ValidationException::withMessages([
                'name' => 'Only a super admin can create the super_admin role.',
            ]);
        }
    }
}
