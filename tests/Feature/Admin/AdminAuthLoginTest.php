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

it('redirects authenticated users to the dashboard when posting login again', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    actingAs($user)
        ->post(route('admin.login.submit'), [
            'email' => $user->email,
            'password' => 'password',
            'fingerprint' => 'any-device',
        ])
        ->assertRedirect(route('admin.dashboard'));
});

it('redirects expired csrf login posts back to the login form', function () {
    $request = \Illuminate\Http\Request::create('/admin/login', 'POST', [
        'email' => 'admin@example.com',
        'password' => 'secret',
    ]);
    $request->setLaravelSession(app('session')->driver());

    $response = app(\Illuminate\Contracts\Debug\ExceptionHandler::class)
        ->render($request, new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'));

    expect($response->isRedirect())->toBeTrue();
    expect($response->headers->get('Location'))->toContain('/admin/login');
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
