<?php

use App\Models\AisensyAccount;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createSuperAdmin(): User
{
    $permissions = [
        'view donations',
        'view all donations',
        'manage donations',
        'view subscriptions',
        'manage subscriptions',
        'view donors',
        'manage causes',
        'manage packages',
        'manage aisensy accounts',
        'manage users',
        'manage settings',
        'manage receipts',
        'view analytics',
        'view reports',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

it('renders all primary admin inertia pages for a super admin', function () {
    $user = createSuperAdmin();
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->create(['cause_id' => $cause->id]);
    $account = AisensyAccount::create([
        'name' => 'Default',
        'api_key' => 'test-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));

    actingAs($user)
        ->get(route('admin.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Analytics/Index'));

    actingAs($user)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Reports/Index'));

    actingAs($user)
        ->get(route('admin.donations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Donations/Index'));

    actingAs($user)
        ->get(route('admin.donations.recovery'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/CheckoutRecovery/Index'));

    actingAs($user)
        ->get(route('admin.subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Subscriptions/Index'));

    actingAs($user)
        ->get(route('admin.donations.offline'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Donations/Offline'));

    actingAs($user)
        ->get(route('admin.donations.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Donations/Create'));

    actingAs($user)
        ->get(route('admin.donors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Donors/Index'));

    actingAs($user)
        ->get(route('admin.causes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Causes/Index'));

    actingAs($user)
        ->get(route('admin.campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Campaigns/Index'));

    actingAs($user)
        ->get(route('admin.campaigns.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Campaigns/Form'));

    actingAs($user)
        ->get(route('admin.causes.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Causes/Create'));

    actingAs($user)
        ->get(route('admin.causes.edit', $cause))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Causes/Edit'));

    actingAs($user)
        ->get("/admin/causes/{$cause->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Causes/Edit'));

    actingAs($user)
        ->get("/admin/causes/{$cause->id}/packages/{$package->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Packages/Form')->where('isEdit', true));

    actingAs($user)
        ->get(route('admin.packages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Packages/Index'));

    actingAs($user)
        ->get(route('admin.causes.packages.create', $cause))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Packages/Form'));

    actingAs($user)
        ->get(route('admin.causes.packages.edit', [$cause, $package]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Packages/Form')->where('isEdit', true));

    actingAs($user)
        ->get(route('admin.aisensy-accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/AisensyAccounts/Index'));

    actingAs($user)
        ->get(route('admin.aisensy-accounts.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/AisensyAccounts/Form'));

    actingAs($user)
        ->get(route('admin.aisensy-accounts.edit', $account))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/AisensyAccounts/Form')->where('isEdit', true));

    actingAs($user)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index'));

    actingAs($user)
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Form')->where('isEdit', false));

    actingAs($user)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Roles/Index'));

    actingAs($user)
        ->get(route('admin.roles.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Roles/Form'));

    actingAs($user)
        ->get(route('admin.permissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Permissions/Index'));

    actingAs($user)
        ->get(route('admin.permissions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Permissions/Form'));

    actingAs($user)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Settings/Index'));

    actingAs($user)
        ->get(route('admin.settings.branding.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Branding/Edit'));
});

it('still renders admin login with blade layout', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertViewIs('admin.auth.login');
});
