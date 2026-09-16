<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('renders flasher notifications on the admin login layout', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('flasher-js', false);
    $response->assertSee('window.flasher.render', false);
});

it('renders flasher notifications and admin notifier on admin pages', function () {
    Permission::firstOrCreate(['name' => 'manage aisensy accounts']);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::findByName('admin')->givePermissionTo('manage aisensy accounts');

    $user = User::factory()->create();
    $user->assignRole('admin');

    $postResponse = $this
        ->actingAs($user)
        ->post(route('admin.aisensy-accounts.store'), [
            'name' => 'India Account',
            'api_key' => 'test-api-key',
            'project_id' => 'project-test-id',
            'country_code' => 'IN',
            'is_active' => 1,
        ]);

    $postResponse->assertRedirect(route('admin.aisensy-accounts.index'));
    $postResponse->assertSessionHas('status');

    $response = $this
        ->actingAs($user)
        ->get(route('admin.aisensy-accounts.index'));

    $response->assertOk();
    $response->assertSee('csrf-token', false);
    $response->assertSee('flasher-js', false);
    $response->assertSee('window.flasher.render', false);
    $response->assertSee('Admin\/AisensyAccounts\/Index', false);
});
