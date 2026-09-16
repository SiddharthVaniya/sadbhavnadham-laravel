<?php

use App\Models\User;
use App\Support\AdminInertiaResources;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('blocks empty named admin roles from the portal without permissions', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('admin');

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('prevents non super admins from assigning the super_admin role', function () {
    Permission::firstOrCreate(['name' => 'manage users']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->syncPermissions(['manage users']);

    $actor = User::factory()->create();
    $actor->assignRole($adminRole);

    actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'Escalated',
            'email' => 'escalated@example.com',
            'password' => 'secret12',
            'roles' => ['super_admin'],
        ])
        ->assertSessionHasErrors('roles');

    expect(User::query()->where('email', 'escalated@example.com')->exists())->toBeFalse();
});

it('prevents non super admins from editing the super_admin role', function () {
    Permission::firstOrCreate(['name' => 'manage users']);
    Permission::firstOrCreate(['name' => 'view donations']);
    $super = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->syncPermissions(['manage users']);

    $actor = User::factory()->create();
    $actor->assignRole($adminRole);

    actingAs($actor)
        ->get(route('admin.roles.edit', $super))
        ->assertForbidden();
});

it('scopes staff options to portal staff without emails', function () {
    foreach (AdminPermissions::allPermissionNames() as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donors', 'manage donor crm']);

    $staff = User::factory()->create(['name' => 'Staff Person', 'email' => 'staff-secret@example.com']);
    $staff->assignRole($role);

    $outsider = User::factory()->create(['name' => 'Outsider', 'email' => 'outsider@example.com']);

    $options = AdminInertiaResources::staffOptions();
    $labels = collect($options)->pluck('label')->all();
    $ids = collect($options)->pluck('value')->all();

    expect($ids)->toContain($staff->id)
        ->and($ids)->not->toContain($outsider->id)
        ->and(implode(' ', $labels))->not->toContain('staff-secret@example.com')
        ->and($labels)->toContain('Staff Person');
});
