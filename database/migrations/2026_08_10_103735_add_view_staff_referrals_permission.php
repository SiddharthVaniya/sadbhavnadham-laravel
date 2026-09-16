<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::findOrCreate('view staff referrals');

        foreach (['super_admin', 'admin', 'accountant', 'manager', 'user'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::query()->where('name', 'view staff referrals')->first();

        if (! $permission) {
            return;
        }

        foreach (Role::query()->get() as $role) {
            $role->revokePermissionTo($permission);
        }

        $permission->delete();
    }
};
