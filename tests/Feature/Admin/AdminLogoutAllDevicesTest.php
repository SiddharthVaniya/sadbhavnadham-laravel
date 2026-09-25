<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('logs out all devices by bumping session version', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'session_version' => 0,
    ]);
    $user->assignRole('admin');

    actingAs($user)
        ->withSession(['auth_session_version' => 0])
        ->post(route('admin.logout-all-devices'))
        ->assertRedirect(route('login'));

    $user->refresh();

    expect($user->session_version)->toBe(1);
    $this->assertGuest();
});

it('rejects requests when session version no longer matches', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'session_version' => 2,
    ]);
    $user->assignRole('admin');

    actingAs($user)
        ->withSession(['auth_session_version' => 0])
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('uses a 30 minute session lifetime', function () {
    expect((int) config('session.lifetime'))->toBe(30);
});
