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

        foreach ([
            'view donations',
            'manage donations',
            'manage receipts',
        ] as $permission) {
            Permission::findOrCreate($permission);
        }

        $role = Role::findOrCreate('user');
        $role->syncPermissions([
            'view donations',
            'manage donations',
            'manage receipts',
        ]);
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::query()->where('name', 'user')->first();

        if ($role) {
            $role->syncPermissions([]);
            $role->delete();
        }
    }
};
