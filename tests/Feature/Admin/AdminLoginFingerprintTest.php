<?php

use App\Models\User;
use App\Models\UserDeviceFingerprint;
use App\Models\UserLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'country' => 'India',
            'regionName' => 'Gujarat',
            'city' => 'Ahmedabad',
        ], 200),
    ]);
});

function makeSuperAdmin(array $overrides = []): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view analytics']);
    $role->givePermissionTo('view analytics');

    $user = User::factory()->create(array_merge([
        'email' => 'super@example.com',
        'password' => 'secret-pass',
        'device_fingerprint' => null,
    ], $overrides));
    $user->assignRole('super_admin');

    return $user;
}

it('blocks admin login without a trusted fingerprint', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view analytics']);
    $user = User::factory()->create([
        'email' => 'staff@example.com',
        'password' => 'secret-pass',
    ]);
    $user->assignRole('admin');

    $this->from(route('login'))
        ->post(route('admin.login.submit'), [
            'email' => 'staff@example.com',
            'password' => 'secret-pass',
            'fingerprint' => 'staff-device-111',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'Device unrecognized. Error id: staff-device-111',
        ]);

    $this->assertGuest();
});

it('blocks super admin login when fingerprint was not added manually', function () {
    $user = makeSuperAdmin();

    $this->from(route('login'))
        ->post(route('admin.login.submit'), [
            'email' => 'super@example.com',
            'password' => 'secret-pass',
            'fingerprint' => 'visitor-abc-12345',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'Device unrecognized. Error id: visitor-abc-12345',
        ]);

    $this->assertGuest();
    expect(UserDeviceFingerprint::query()->where('user_id', $user->id)->count())->toBe(0);
    expect(UserLoginLog::query()->first()?->status)->toBe(UserLoginLog::STATUS_BLOCKED_FINGERPRINT);
});

it('does not auto-register a second fingerprint on login', function () {
    $user = makeSuperAdmin();
    UserDeviceFingerprint::query()->create([
        'user_id' => $user->id,
        'fingerprint' => 'device-one',
        'last_used_at' => now(),
    ]);

    $this->from(route('login'))
        ->post(route('admin.login.submit'), [
            'email' => 'super@example.com',
            'password' => 'secret-pass',
            'fingerprint' => 'device-two',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(UserDeviceFingerprint::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('allows login when fingerprint already exists on the account', function () {
    $user = makeSuperAdmin();
    UserDeviceFingerprint::query()->create([
        'user_id' => $user->id,
        'fingerprint' => 'known-device-fingerprint',
        'last_used_at' => now()->subDay(),
    ]);

    $this->from(route('login'))
        ->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->post(route('admin.login.submit'), [
            'email' => 'super@example.com',
            'password' => 'secret-pass',
            'fingerprint' => 'known-device-fingerprint',
        ])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(UserLoginLog::query()->first()?->location)->toBe('Ahmedabad, Gujarat, India');
    expect(session('auth_device_fingerprint'))->toBe('known-device-fingerprint');
});

it('signs out a non-marketer whose session fingerprint is not trusted', function () {
    $user = makeSuperAdmin();

    $this->actingAs($user)
        ->withSession(['auth_device_fingerprint' => 'not-in-database'])
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('does not require fingerprint for digital marketers', function () {
    Role::firstOrCreate(['name' => 'digital_marketer', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'email' => 'marketer@example.com',
        'password' => 'secret-pass',
    ]);
    $user->assignRole('digital_marketer');

    $this->from(route('login'))
        ->post(route('admin.login.submit'), [
            'email' => 'marketer@example.com',
            'password' => 'secret-pass',
        ])
        ->assertRedirect(route('marketer.dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(UserDeviceFingerprint::query()->where('user_id', $user->id)->count())->toBe(0);
});
