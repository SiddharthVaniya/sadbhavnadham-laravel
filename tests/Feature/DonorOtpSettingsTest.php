<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows saving the aisensy otp campaign from settings', function () {
    Permission::firstOrCreate(['name' => 'manage settings']);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->givePermissionTo('manage settings');

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $setting = Setting::query()->where('key', Setting::AISENSY_OTP_CAMPAIGN)->first();
    expect($setting)->not->toBeNull();

    $this->actingAs($user)
        ->patchJson(route('admin.settings.update', $setting), [
            'value' => 'donor-otp-verification',
        ])
        ->assertOk()
        ->assertJsonFragment(['value' => 'donor-otp-verification']);

    expect($setting->fresh()->value)->toBe('donor-otp-verification')
        ->and(Setting::getValue(Setting::AISENSY_OTP_CAMPAIGN))->toBe('donor-otp-verification');
});
