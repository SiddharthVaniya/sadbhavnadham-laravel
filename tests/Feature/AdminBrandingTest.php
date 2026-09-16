<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\BrandingStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createBrandingAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage settings']);

    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->givePermissionTo('manage settings');

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

it('allows settings managers to view the branding page', function () {
    $user = createBrandingAdmin();

    $this->actingAs($user)
        ->get(route('admin.settings.branding.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Branding/Edit')
            ->has('branding.name'));
});

it('blocks users without manage settings permission from branding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.branding.edit'))
        ->assertForbidden();
});

it('persists branding overrides and applies them on public pages', function () {
    $user = createBrandingAdmin();

    $this->actingAs($user)
        ->post(route('admin.settings.branding.update'), [
            'name' => 'Helping Hands Ashram',
            'short_name' => 'Helping Hands',
            'tagline' => 'Care with compassion every day.',
            'bank_account_number' => '9988776655',
            'bank_ifsc' => 'TEST0001234',
            'bank_name' => 'Test Bank',
            'contact_email' => 'care@helpinghands.test',
            'logo_existing' => '/images/logo_main.png',
            'logo_public_existing' => '/images/logo_main.png',
            'favicon_existing' => '/assets/img/logo/favicon.png',
            'og_image_existing' => '/images/logo_main.png',
        ])
        ->assertRedirect(route('admin.settings.branding.edit'));

    BrandingStore::flush();
    BrandingStore::applyToConfig();

    expect(Setting::query()->where('key', Setting::BRANDING_OVERRIDES)->exists())->toBeTrue();

    $this->get(route('donate.index'))
        ->assertOk()
        ->assertSee('Helping Hands Ashram', false)
        ->assertSee('Care with compassion every day.', false);

    $this->get(route('donate.bank-details'))
        ->assertOk()
        ->assertSee('9988776655', false)
        ->assertSee('TEST0001234', false);
});

it('excludes branding overrides from notification settings list', function () {
    $user = createBrandingAdmin();

    BrandingStore::persist(['name' => 'Stored Name']);

    $this->actingAs($user)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('settings', 13)
            ->where('settings', fn ($settings) => collect($settings)->pluck('key')->doesntContain(Setting::BRANDING_OVERRIDES)));
});

it('clears empty branding fields so env defaults apply', function () {
    BrandingStore::persist([
        'name' => 'Temporary Name',
        'tagline' => 'Temporary tagline',
    ]);

    BrandingStore::flush();

    config(['branding.name' => 'Env Default Name']);

    expect(config('branding.name'))->toBe('Env Default Name');

    $user = createBrandingAdmin();

    $this->actingAs($user)
        ->post(route('admin.settings.branding.update'), [
            'name' => '',
            'tagline' => '',
            'logo_existing' => '',
            'logo_public_existing' => '',
            'favicon_existing' => '',
            'og_image_existing' => '',
        ]);

    BrandingStore::flush();

    $overrides = BrandingStore::overrides();

    expect($overrides)->toBe([]);
});
