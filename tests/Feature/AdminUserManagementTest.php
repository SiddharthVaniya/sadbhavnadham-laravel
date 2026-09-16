<?php

use App\Models\Department;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

uses(RefreshDatabase::class);

function actingManageUsersSuperAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage users']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $actor = User::factory()->create();
    $actor->assignRole('super_admin');

    return $actor;
}

it('soft deletes a user and allows restore', function () {
    $actor = actingManageUsersSuperAdmin();
    $target = User::factory()->create();
    $target->assignRole('admin');

    actingAs($actor)
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect()
        ->assertSessionHas('status');

    assertSoftDeleted('users', ['id' => $target->id]);

    actingAs($actor)
        ->post(route('admin.users.restore', $target->id))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(User::query()->find($target->id))->not->toBeNull();
});

it('prevents users from deleting their own account', function () {
    $actor = actingManageUsersSuperAdmin();

    actingAs($actor)
        ->delete(route('admin.users.destroy', $actor))
        ->assertForbidden();

    expect(User::query()->find($actor->id))->not->toBeNull();
});

it('prevents deleting super admin accounts', function () {
    $actor = actingManageUsersSuperAdmin();
    $otherSuper = User::factory()->create();
    $otherSuper->assignRole('super_admin');

    actingAs($actor)
        ->delete(route('admin.users.destroy', $otherSuper))
        ->assertForbidden();

    expect(User::query()->find($otherSuper->id))->not->toBeNull();
});

it('filters users by department and archived status', function () {
    $actor = actingManageUsersSuperAdmin();
    $department = Department::factory()->create(['name' => 'Finance']);
    $active = User::factory()->create(['department_id' => $department->id, 'name' => 'Finance User']);
    $active->assignRole('admin');

    $archived = User::factory()->create(['name' => 'Archived User']);
    $archived->assignRole('admin');
    $archived->delete();

    actingAs($actor)
        ->get(route('admin.users.index', ['department_id' => $department->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Finance User'));

    actingAs($actor)
        ->get(route('admin.users.index', ['status' => 'archived']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Archived User'));
});

it('stores a user with a department', function () {
    $actor = actingManageUsersSuperAdmin();
    $department = Department::factory()->create();

    actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'Dept Staff',
            'email' => 'dept@example.com',
            'password' => 'secret12',
            'department_id' => $department->id,
            'roles' => ['admin'],
        ])
        ->assertRedirect(route('admin.users.index'));

    assertDatabaseHas('users', [
        'email' => 'dept@example.com',
        'department_id' => $department->id,
    ]);
});

it('prevents removing the last super admin role', function () {
    $actor = actingManageUsersSuperAdmin();

    actingAs($actor)
        ->put(route('admin.users.update', $actor), [
            'name' => $actor->name,
            'email' => $actor->email,
            'password' => '',
            'roles' => ['admin'],
        ])
        ->assertSessionHasErrors('roles');

    expect($actor->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('allows assigning direct user-only permissions on top of roles', function () {
    Permission::firstOrCreate(['name' => 'manage users']);
    Permission::firstOrCreate(['name' => AdminPermissions::CAUSE_VIEW]);
    Permission::firstOrCreate(['name' => AdminPermissions::CAUSE_COPY_LINKS]);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $actor = actingManageUsersSuperAdmin();
    $target = User::factory()->create();
    $target->assignRole('user');

    actingAs($actor)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'roles' => ['user'],
            'permissions' => [
                AdminPermissions::CAUSE_VIEW,
                AdminPermissions::CAUSE_COPY_LINKS,
            ],
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    $target->refresh();

    expect($target->hasDirectPermission(AdminPermissions::CAUSE_VIEW))->toBeTrue()
        ->and($target->hasDirectPermission(AdminPermissions::CAUSE_COPY_LINKS))->toBeTrue()
        ->and($target->can(AdminPermissions::CAUSE_VIEW))->toBeTrue()
        ->and($target->can(AdminPermissions::CAUSE_CREATE))->toBeFalse();

    actingAs($actor)
        ->get(route('admin.users.edit', $target))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Form')
            ->has('permissionGroups', 11)
            ->where('user.direct_permissions', [
                AdminPermissions::CAUSE_VIEW,
                AdminPermissions::CAUSE_COPY_LINKS,
            ]));
});

it('includes a partner attribution link for users with referral codes', function () {
    Permission::firstOrCreate(['name' => 'manage users']);
    Permission::firstOrCreate(['name' => 'view staff referrals']);

    $actor = actingManageUsersSuperAdmin();
    $partner = User::factory()->create([
        'name' => 'Referral Partner',
        'referral_code' => 'ac',
    ]);

    actingAs($actor)
        ->get(route('admin.users.index', ['search' => 'Referral Partner']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->where('users.data.0.id', $partner->id)
            ->where(
                'users.data.0.referrals_href',
                route('admin.referrals.index', ['partner_user_id' => $partner->id, 'duration' => 'all'])
            ));
});

it('blocks archived users from admin login scope', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $user->delete();

    expect(User::query()->where('email', $user->email)->exists())->toBeFalse()
        ->and(User::withTrashed()->where('email', $user->email)->exists())->toBeTrue();
});
