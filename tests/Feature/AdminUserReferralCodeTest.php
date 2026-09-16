<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function actingManageUsersAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage users']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['manage users']);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor;
}

it('stores a referral code when creating an admin user', function () {
    $actor = actingManageUsersAdmin();

    actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'Paz Staff',
            'email' => 'paz@example.com',
            'password' => 'secret12',
            'referral_code' => 'Paz',
            'roles' => ['admin'],
        ])
        ->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'paz@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->referral_code)->toBe('paz');
});

it('updates a referral code for an existing user', function () {
    $actor = actingManageUsersAdmin();
    $target = User::factory()->create(['referral_code' => null]);
    $target->assignRole('admin');

    actingAs($actor)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'referral_code' => 'staff-riya',
            'roles' => ['admin'],
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($target->fresh()->referral_code)->toBe('staff-riya');
});

it('rejects reserved and duplicate referral codes', function () {
    $actor = actingManageUsersAdmin();
    User::factory()->withReferralCode('paz')->create();

    actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'Reserved',
            'email' => 'reserved@example.com',
            'password' => 'secret12',
            'referral_code' => 'admin',
            'roles' => ['admin'],
        ])
        ->assertSessionHasErrors('referral_code');

    actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'Duplicate',
            'email' => 'duplicate@example.com',
            'password' => 'secret12',
            'referral_code' => 'paz',
            'roles' => ['admin'],
        ])
        ->assertSessionHasErrors('referral_code');
});

it('shares the authenticated users referral code with inertia', function () {
    Permission::firstOrCreate(['name' => 'view donations']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->withReferralCode('paz')->create();
    $user->assignRole('super_admin');

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.referral_code', 'paz'));
});

it('stores a donation target from the admin user form', function () {
    $actor = actingManageUsersAdmin();

    actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'Ashvini Marketer',
            'email' => 'ashvini-target@example.com',
            'password' => 'secret12',
            'referral_code' => 'ashvini',
            'donation_target' => 200,
            'roles' => ['admin'],
        ])
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->where('email', 'ashvini-target@example.com')->value('donation_target'))->toBe(200);
});

it('updates a donation target for an existing user', function () {
    $actor = actingManageUsersAdmin();
    $target = User::factory()->create(['donation_target' => 50]);
    $target->assignRole('admin');

    actingAs($actor)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'donation_target' => 200,
            'roles' => ['admin'],
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($target->fresh()->donation_target)->toBe(200);
});
