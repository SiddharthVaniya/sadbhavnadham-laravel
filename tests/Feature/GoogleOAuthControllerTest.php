<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createGoogleOAuthSettingsUser(): User
{
    Permission::firstOrCreate(['name' => 'manage settings', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('manage settings');

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('requires authentication before starting google authorization', function () {
    $response = $this->get('/google-auth');

    $response->assertRedirect(route('login'));
});

it('requires settings permission before starting google authorization', function () {
    Permission::firstOrCreate(['name' => 'view donations', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
    $role->givePermissionTo('view donations');

    $user = User::factory()->create();
    $user->assignRole('accountant');

    $response = $this->actingAs($user)->get('/google-auth');

    $response->assertForbidden();
});
it('builds google authorization url from configured credentials', function () {
    config()->set('services.google.client_id', 'test-client-id');
    config()->set('services.google.client_secret', 'test-client-secret');
    config()->set('services.google.redirect_uri', 'https://example.test/oauth2callback');

    $response = $this->actingAs(createGoogleOAuthSettingsUser())->get('/google-auth');

    $response->assertRedirect();

    $state = session('google_oauth_state');
    $location = urldecode((string) $response->headers->get('Location'));

    expect($location)->toContain('accounts.google.com');
    expect($location)->toContain('client_id=test-client-id');
    expect($location)->toContain('redirect_uri=https://example.test/oauth2callback');
    expect($location)->toContain('state='.$state);
});

it('returns validation-style response when callback code is missing', function () {
    $response = $this
        ->actingAs(createGoogleOAuthSettingsUser())
        ->withSession(['google_oauth_state' => 'valid-state'])
        ->get('/oauth2callback?state=valid-state');

    $response->assertUnprocessable();
    $response->assertSeeText('Authorization code is missing');
});

it('returns error response when google sends oauth error', function () {
    $response = $this
        ->actingAs(createGoogleOAuthSettingsUser())
        ->withSession(['google_oauth_state' => 'valid-state'])
        ->get('/oauth2callback?state=valid-state&error=access_denied');

    $response->assertUnprocessable();
    $response->assertSeeText('Google authorization failed: access_denied');
});

it('rejects callbacks that do not match the stored oauth state', function () {
    $response = $this
        ->actingAs(createGoogleOAuthSettingsUser())
        ->withSession(['google_oauth_state' => 'valid-state'])
        ->get('/oauth2callback?state=attacker-state&code=test-code');

    $response->assertUnprocessable();
    $response->assertSeeText('Invalid Google OAuth state');
});
