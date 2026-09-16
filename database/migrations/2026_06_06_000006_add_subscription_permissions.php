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

        $view = Permission::firstOrCreate([
            'name' => 'view subscriptions',
            'guard_name' => 'web',
        ]);

        $manage = Permission::firstOrCreate([
            'name' => 'manage subscriptions',
            'guard_name' => 'web',
        ]);

        $rolePermissions = [
            'super_admin' => [$view, $manage],
            'admin' => [$view, $manage],
            'accountant' => [$view],
            'manager' => [$view],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', ['view subscriptions', 'manage subscriptions'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
