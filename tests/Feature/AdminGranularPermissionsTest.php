<?php

use App\Models\Cause;
use App\Models\DonationCampaign;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function userWithPermissions(array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $sorted = $permissions;
    sort($sorted);

    $role = Role::firstOrCreate(['name' => 'perm-'.md5(implode(',', $sorted)), 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

it('allows link-only staff to view causes and copy links but not create or delete', function () {
    $user = userWithPermissions([
        AdminPermissions::CAUSE_VIEW,
        AdminPermissions::CAUSE_COPY_LINKS,
    ]);

    expect($user->can(AdminPermissions::CAUSE_VIEW))->toBeTrue();

    actingAs($user)
        ->get(route('admin.causes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Causes/Index')
            ->where('abilities.can_create', false)
            ->where('abilities.can_delete', false)
            ->where('abilities.can_copy_links', true));

    actingAs($user)
        ->get(route('admin.causes.create'))
        ->assertForbidden();

    $cause = Cause::factory()->create();

    actingAs($user)
        ->delete(route('admin.causes.destroy', $cause))
        ->assertForbidden();
});

it('allows create-only staff to add causes without delete access', function () {
    $user = userWithPermissions([
        AdminPermissions::CAUSE_VIEW,
        AdminPermissions::CAUSE_CREATE,
    ]);

    actingAs($user)
        ->get(route('admin.causes.create'))
        ->assertOk();

    actingAs($user)
        ->post(route('admin.causes.store'), [
            'slug' => 'granular-cause',
            'title' => 'Granular Cause',
            'is_active' => true,
            'sort_order' => 1,
            'allow_custom_amount' => true,
            'allow_recurring' => false,
            'pan_required' => true,
        ])
        ->assertRedirect();

    $cause = Cause::query()->where('slug', 'granular-cause')->firstOrFail();

    actingAs($user)
        ->delete(route('admin.causes.destroy', $cause))
        ->assertForbidden();
});

it('allows campaign link-only staff to view campaigns without edit routes', function () {
    $user = userWithPermissions([
        AdminPermissions::CAMPAIGN_VIEW,
        AdminPermissions::CAMPAIGN_COPY_LINKS,
    ]);

    $campaign = DonationCampaign::factory()->create();

    actingAs($user)
        ->get(route('admin.campaigns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campaigns/Index')
            ->where('abilities.can_create', false)
            ->where('abilities.can_edit', false)
            ->where('abilities.can_copy_links', true));

    actingAs($user)
        ->get(route('admin.campaigns.edit', $campaign))
        ->assertForbidden();
});

it('still grants full cause and campaign access through legacy manage causes permission', function () {
    $user = userWithPermissions([AdminPermissions::MANAGE_CAUSES]);
    $cause = Cause::factory()->create();
    $campaign = DonationCampaign::factory()->create();

    actingAs($user)
        ->get(route('admin.causes.create'))
        ->assertOk();

    actingAs($user)
        ->get(route('admin.campaigns.edit', $campaign))
        ->assertOk();

    actingAs($user)
        ->delete(route('admin.causes.destroy', $cause))
        ->assertRedirect();
});

it('exposes grouped permission definitions on the role form', function () {
    $user = userWithPermissions([AdminPermissions::MANAGE_USERS]);

    actingAs($user)
        ->get(route('admin.roles.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Roles/Form')
            ->has('permissionGroups', 11)
            ->where('permissionGroups.0.key', 'causes'));
});

it('allows package link-only staff to view packages without edit or delete', function () {
    $user = userWithPermissions([
        AdminPermissions::PACKAGE_VIEW,
        AdminPermissions::PACKAGE_COPY_LINKS,
    ]);

    actingAs($user)
        ->get(route('admin.packages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Packages/Index')
            ->where('abilities.can_create', false)
            ->where('abilities.can_edit', false)
            ->where('abilities.can_delete', false)
            ->where('abilities.can_copy_links', true));

    $cause = Cause::factory()->create();
    $package = \App\Models\CausePackage::factory()->create(['cause_id' => $cause->id]);

    actingAs($user)
        ->get(route('admin.causes.packages.edit', [$cause, $package]))
        ->assertForbidden();

    actingAs($user)
        ->delete(route('admin.causes.packages.destroy', [$cause, $package]))
        ->assertForbidden();
});

it('allows view-only donor staff without import access', function () {
    $user = userWithPermissions([AdminPermissions::DONOR_VIEW]);

    actingAs($user)
        ->get(route('admin.donors.index'))
        ->assertOk();

    actingAs($user)
        ->get(route('admin.donors.import.template'))
        ->assertForbidden();
});

it('still grants legacy manage packages access to all package routes', function () {
    $user = userWithPermissions([AdminPermissions::MANAGE_PACKAGES]);
    $cause = Cause::factory()->create();

    actingAs($user)
        ->get(route('admin.causes.packages.create', $cause))
        ->assertOk();
});

it('seeds a digital marketer role with link-only permissions', function () {
    $this->seed(\Database\Seeders\AdminRolePermissionSeeder::class);

    $role = Role::findByName('digital_marketer');

    expect($role->hasPermissionTo(AdminPermissions::CAUSE_COPY_LINKS))->toBeTrue()
        ->and($role->hasPermissionTo(AdminPermissions::CAUSE_CREATE))->toBeFalse()
        ->and($role->hasPermissionTo(AdminPermissions::CAMPAIGN_COPY_LINKS))->toBeTrue()
        ->and($role->hasPermissionTo(AdminPermissions::CAMPAIGN_CREATE))->toBeFalse()
        ->and($role->hasPermissionTo(AdminPermissions::PACKAGE_COPY_LINKS))->toBeTrue()
        ->and($role->hasPermissionTo(AdminPermissions::PACKAGE_CREATE))->toBeFalse();
});
