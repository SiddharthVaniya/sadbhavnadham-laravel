<?php

use App\Models\DonationOrder;
use App\Models\User;
use App\Support\DonationVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

function createReceiptClerk(): User
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

function createViewAllDonationsAdmin(): User
{
    foreach (['view donations', 'view all donations', 'manage donations', 'manage receipts'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations', 'manage donations', 'manage receipts']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function createOwnedOfflineOrder(User $creator, array $overrides = []): DonationOrder
{
    return DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_OFFLINE,
        'source_channel' => 'offline',
        'provider_order_id' => 'manual-own-'.fake()->unique()->numerify('#####'),
        'created_by' => $creator->id,
        'donor_name' => 'Own Donor',
        'donor_email' => 'own@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ], $overrides));
}

it('stamps created_by when a clerk records an offline donation', function () {
    $clerk = createReceiptClerk();

    actingAs($clerk)
        ->post(route('admin.donations.store'), [
            'donor_name' => 'Clerk Donor',
            'donor_email' => '',
            'donor_phone' => '9876543210',
            'address' => '123 Test Street',
            'pincode' => '360001',
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'country' => 'INDIA',
            'payment_provider' => 'offline',
            'total_amount' => 1500,
            'send_receipt_email' => false,
        ])
        ->assertRedirect();

    $order = DonationOrder::query()->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->created_by)->toBe($clerk->id);
});

it('lists only donations created by the clerk', function () {
    $clerk = createReceiptClerk();
    $other = createReceiptClerk();

    $own = createOwnedOfflineOrder($clerk, ['donor_name' => 'Mine']);
    createOwnedOfflineOrder($other, ['donor_name' => 'Theirs']);

    actingAs($clerk)
        ->get(route('admin.donations.index', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 1)
            ->where('donations.data.0.id', $own->id)
            ->where('donations.data.0.donor_name', 'Mine'));
});

it('forbids a clerk from viewing another staff members donation', function () {
    $clerk = createReceiptClerk();
    $other = createReceiptClerk();
    $order = createOwnedOfflineOrder($other);

    actingAs($clerk)
        ->get(route('admin.donations.show', $order))
        ->assertForbidden();
});

it('allows admins with view all donations to see every donation', function () {
    $admin = createViewAllDonationsAdmin();
    $clerk = createReceiptClerk();

    createOwnedOfflineOrder($clerk, ['donor_name' => 'Clerk Order']);
    createOwnedOfflineOrder($admin, ['donor_name' => 'Admin Order']);

    actingAs($admin)
        ->get(route('admin.donations.index', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 2));
});

it('exposes the view all donations permission constant', function () {
    expect(DonationVisibility::VIEW_ALL)->toBe('view all donations');
});
