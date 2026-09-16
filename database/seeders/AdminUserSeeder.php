<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Ensure Super Admin Role Exists
        |--------------------------------------------------------------------------
        */

        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create / Update Admin User
        |--------------------------------------------------------------------------
        */

        $admin = User::updateOrCreate(
            ['email' => 'info@sadbhavnadham.org'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin@12345'),
                'email_verified_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Assign Role
        |--------------------------------------------------------------------------
        */

        if (!$admin->hasRole('super_admin')) {
            $admin->assignRole($role);
        }
    }
}
