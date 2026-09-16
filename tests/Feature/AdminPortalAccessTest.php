<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows a custom-role receipt clerk into the admin portal', function () {
    foreach (['view donations', 'manage donations', 'manage receipts'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'receipt_clerk', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'manage donations', 'manage receipts']);

    $user = User::factory()->create();
    $user->assignRole($role);

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.donations.create'));
});

it('allows the seeded user role into the admin portal', function () {
    foreach (['view donations', 'manage donations', 'manage receipts'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'manage donations', 'manage receipts']);

    $user = User::factory()->create();
    $user->assignRole($role);

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.donations.create'));
});

it('still blocks users with no admin permissions', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('blocks a named admin role that has zero permissions', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('admin');

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
