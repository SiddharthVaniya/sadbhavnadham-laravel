<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows admin login page for guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in', false)
        ->assertSee('id="toggle-password"', false)
        ->assertSee('fingerprintjs.min.js', false)
        ->assertSee('object-contain', false)
        ->assertDontSee('Dashboard', false);
});

it('shows friendly validation messages on admin login', function () {
    $this->from(route('login'))
        ->post(route('admin.login.submit'), [
            'email' => '',
            'password' => '',
            'fingerprint' => 'visitor-test-fingerprint',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'Please enter your admin email address.',
            'password' => 'Please enter your password.',
        ]);
});

it('shows a clear message for invalid admin credentials', function () {
    $this->from(route('login'))
        ->post(route('admin.login.submit'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
            'fingerprint' => 'visitor-test-fingerprint',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'The email or password you entered is incorrect.',
        ]);
});

it('redirects authenticated users away from admin login page', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('admin.dashboard'));
});

it('forces a full page redirect to login after inertia logout', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'test',
        ])
        ->post(route('admin.logout'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));
});
