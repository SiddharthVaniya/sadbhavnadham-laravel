<?php

use App\Models\Cause;
use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('prevents donation viewers from managing aisensy accounts', function () {
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'manage aisensy accounts']);

    $role = Role::firstOrCreate(['name' => 'manager']);
    $role->givePermissionTo('view donations');

    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this
        ->actingAs($user)
        ->get(route('admin.aisensy-accounts.index'));

    $response->assertForbidden();
});

it('prevents donation viewers from recording paid offline donations', function () {
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'manage donations']);

    $role = Role::firstOrCreate(['name' => 'manager']);
    $role->givePermissionTo('view donations');

    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), [
            'donor_name' => 'Offline Donor',
            'donor_email' => 'offline@example.com',
            'donor_phone' => '8888888888',
            'address' => '123 Test Street',
            'pincode' => '360001',
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'country' => 'India',
            'payment_provider' => 'offline',
            'total_amount' => 1500,
        ]);

    $response->assertForbidden();
    expect(DonationOrder::where('donor_email', 'offline@example.com')->exists())->toBeFalse();
});

it('requires settings permission before starting google sheets oauth', function () {
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'manage settings']);

    $role = Role::firstOrCreate(['name' => 'manager']);
    $role->givePermissionTo('view donations');

    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this
        ->actingAs($user)
        ->get('/google-auth');

    $response->assertForbidden();
});

it('rejects wordpress razorpay requests when the api token is not configured', function () {
    config()->set('app.wp_api_token', null);

    $cause = Cause::factory()->create();

    $response = $this->postJson('/api/wp-razorpay', [
        'cause' => $cause->slug,
        'amount' => 500,
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9999999999',
        'address' => '123 Test Street',
        'pincode' => '360001',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'country' => 'India',
        'consent_indian_citizen' => '1',
        'pan_number' => 'ABCDE1234F',
    ]);

    $response->assertForbidden();
});

it('rate limits repeated admin login failures', function () {
    cache()->flush();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('admin.login.submit'), [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect();
    }

    $response = $this->post(route('admin.login.submit'), [
        'email' => 'missing@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
