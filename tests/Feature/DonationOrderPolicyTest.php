<?php

use App\Models\DonationOrder;
use App\Models\User;
use App\Policies\DonationOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createPolicyClerk(): User
{
    foreach (['view donations', 'manage donations', 'manage receipts'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'manage donations', 'manage receipts']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function createPolicyViewerAll(): User
{
    foreach (['view donations', 'view all donations', 'manage donations'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations', 'manage donations']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows clerks to view and update only their own donations via policy', function () {
    $clerk = createPolicyClerk();
    $other = createPolicyClerk();
    $policy = new DonationOrderPolicy;

    $own = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_OFFLINE,
        'provider_order_id' => 'manual-policy-own',
        'created_by' => $clerk->id,
        'donor_name' => 'Own',
        'donor_email' => 'own@example.com',
        'donor_phone' => '9000000001',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $theirs = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_OFFLINE,
        'provider_order_id' => 'manual-policy-other',
        'created_by' => $other->id,
        'donor_name' => 'Other',
        'donor_email' => 'other@example.com',
        'donor_phone' => '9000000002',
        'currency' => 'INR',
        'total_amount' => 200,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    expect($policy->view($clerk, $own))->toBeTrue()
        ->and($policy->update($clerk, $own))->toBeTrue()
        ->and($policy->view($clerk, $theirs))->toBeFalse()
        ->and($policy->update($clerk, $theirs))->toBeFalse()
        ->and($policy->create($clerk))->toBeTrue();

    actingAs($clerk)
        ->get(route('admin.donations.show', $theirs))
        ->assertForbidden();
});

it('allows view-all staff to view any donation through the policy', function () {
    $admin = createPolicyViewerAll();
    $clerk = createPolicyClerk();
    $policy = new DonationOrderPolicy;

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_OFFLINE,
        'provider_order_id' => 'manual-policy-admin',
        'created_by' => $clerk->id,
        'donor_name' => 'Clerk Order',
        'donor_email' => 'clerk-order@example.com',
        'donor_phone' => '9000000003',
        'currency' => 'INR',
        'total_amount' => 300,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    expect($policy->view($admin, $order))->toBeTrue()
        ->and($policy->update($admin, $order))->toBeTrue();

    actingAs($admin)
        ->get(route('admin.donations.show', $order))
        ->assertOk();
});
