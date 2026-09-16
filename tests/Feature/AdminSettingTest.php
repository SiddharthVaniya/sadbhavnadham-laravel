<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createSettingsUser(): User
{
    Permission::firstOrCreate(['name' => 'manage settings']);

    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->givePermissionTo('manage settings');

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

it('allows a user with manage settings permission to view the settings page', function () {
    $user = createSettingsUser();

    $this->actingAs($user)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Index')
            ->has('settings', 15)
            ->where('settings', fn ($settings) => collect($settings)->pluck('label')->contains('Send Donation Receipt Email'))
            ->where('settings', fn ($settings) => collect($settings)->pluck('key')->contains(Setting::RECONCILE_NOTIFICATION_MAX_ATTEMPTS))
            ->where('settings', fn ($settings) => collect($settings)->pluck('key')->contains(Setting::ATTACH_RECEIPT_PDF))
            ->where('settings', fn ($settings) => collect($settings)->pluck('key')->contains(Setting::LOG_FAILED_DONATIONS_TO_SHEET))
            ->where('settings', fn ($settings) => collect($settings)->pluck('key')->contains(Setting::AISENSY_OTP_CAMPAIGN))
            ->where('settings', fn ($settings) => collect($settings)->pluck('key')->contains(Setting::AISENSY_OTP_ACCOUNT_ID)));
});

it('updates birthday campaign setting value via PATCH', function () {
    $user = createSettingsUser();

    $setting = Setting::where('key', Setting::AISENSY_BIRTHDAY_CAMPAIGN)->first();
    expect($setting)->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->patchJson(route('admin.settings.update', $setting), [
            'value' => 'my-birthday-campaign',
        ]);

    $response->assertOk();
    $response->assertJsonFragment(['value' => 'my-birthday-campaign']);

    expect($setting->fresh()->value)->toBe('my-birthday-campaign');
});

it('blocks users without manage settings permission', function () {
    Permission::firstOrCreate(['name' => 'view donations']);

    $role = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
    $role->givePermissionTo('view donations');

    $user = User::factory()->create();
    $user->assignRole('accountant');

    $response = $this->actingAs($user)->get(route('admin.settings.index'));

    $response->assertForbidden();
});

it('toggles a setting from enabled to disabled via PATCH', function () {
    $user = createSettingsUser();

    $setting = Setting::where('key', Setting::SEND_RECEIPT_EMAIL)->first();
    expect($setting->value)->toBe('1');

    $response = $this
        ->actingAs($user)
        ->patchJson(route('admin.settings.toggle', $setting));

    $response->assertOk();
    $response->assertJsonFragment(['enabled' => false]);

    expect($setting->fresh()->value)->toBe('0');
});

it('toggles a setting from disabled back to enabled', function () {
    $user = createSettingsUser();

    $setting = Setting::where('key', Setting::SEND_WHATSAPP_THANK_YOU)->first();
    $setting->update(['value' => '0']);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('admin.settings.toggle', $setting));

    $response->assertOk();
    $response->assertJsonFragment(['enabled' => true]);

    expect($setting->fresh()->value)->toBe('1');
});

it('Setting::isEnabled returns true when setting value is 1', function () {
    expect(Setting::isEnabled(Setting::SEND_RECEIPT_EMAIL))->toBeTrue();
});

it('Setting::isEnabled returns false when setting value is 0', function () {
    Setting::where('key', Setting::SEND_WHATSAPP_PAYMENT_LINK)->update(['value' => '0']);

    expect(Setting::isEnabled(Setting::SEND_WHATSAPP_PAYMENT_LINK))->toBeFalse();
});

it('Setting::isEnabled defaults to true when key does not exist', function () {
    expect(Setting::isEnabled('non_existent_key'))->toBeTrue();
});
