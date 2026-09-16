<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\DonationNotificationRetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createRetrySettingsUser(): User
{
    Permission::firstOrCreate(['name' => 'manage settings']);

    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->givePermissionTo('manage settings');

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

it('updates max auto-retry attempts from admin settings', function () {
    $user = createRetrySettingsUser();

    $setting = Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_MAX_ATTEMPTS)->first();
    expect($setting)->not->toBeNull();

    $this->actingAs($user)
        ->patchJson(route('admin.settings.update', $setting), ['value' => 7])
        ->assertOk()
        ->assertJsonFragment(['value' => '7']);

    expect($setting->fresh()->value)->toBe('7')
        ->and(DonationNotificationRetry::maxReconcileAttempts())->toBe(7);
});

it('rejects invalid retry values from admin settings', function () {
    $user = createRetrySettingsUser();

    $setting = Setting::query()->where('key', Setting::NOTIFICATION_JOB_TRIES)->first();

    $this->actingAs($user)
        ->patchJson(route('admin.settings.update', $setting), ['value' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);

    expect($setting->fresh()->value)->toBe('3');
});

it('reads retry window hours from admin settings', function () {
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_RETRY_HOURS)->update(['value' => '24']);

    expect(DonationNotificationRetry::retryWindowHours())->toBe(24);
});
