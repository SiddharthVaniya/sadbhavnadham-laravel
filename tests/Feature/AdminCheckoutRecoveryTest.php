<?php

use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\SendPaymentLinkWhatsAppJob;
use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createRecoveryViewer(): User
{
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'view all donations']);

    $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function createRecoveryManager(): User
{
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    Permission::firstOrCreate(['name' => 'manage receipts']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations', 'manage receipts']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function createAbandonedOrder(array $overrides = []): DonationOrder
{
    $createdAt = $overrides['created_at'] ?? now()->subMinutes(20);
    unset($overrides['created_at'], $overrides['updated_at']);

    $order = DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_abandoned_'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Abandoned Donor',
        'donor_email' => 'abandoned@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now()->subMinutes(20),
    ], $overrides));

    $order->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->save();

    return $order->fresh();
}

it('renders the checkout recovery queue for donation viewers', function () {
    $user = createRecoveryViewer();
    $order = createAbandonedOrder();

    actingAs($user)
        ->get(route('admin.donations.recovery', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CheckoutRecovery/Index')
            ->where('stats.total_count', 1)
            ->where('canNudge', false)
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order->id)
            ->where('orders.data.0.can_nudge', true));
});

it('excludes fresh checkouts younger than five minutes', function () {
    $user = createRecoveryViewer();
    createAbandonedOrder([
        'created_at' => now()->subMinutes(2),
        'failed_at' => now()->subMinutes(2),
    ]);

    actingAs($user)
        ->get(route('admin.donations.recovery', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CheckoutRecovery/Index')
            ->where('stats.total_count', 0)
            ->has('orders.data', 0));
});

it('queues payment link creation when nudging a pending abandoned checkout', function () {
    Bus::fake();

    $user = createRecoveryManager();
    $order = createAbandonedOrder([
        'status' => DonationOrder::STATUS_PENDING,
        'failed_at' => null,
    ]);

    actingAs($user)
        ->from(route('admin.donations.recovery'))
        ->post(route('admin.donations.recovery.nudge'), [
            'order_ids' => [$order->id],
        ])
        ->assertRedirect(route('admin.donations.recovery'))
        ->assertSessionHas('status');

    $order->refresh();

    expect($order->isFailed())->toBeTrue()
        ->and($order->failed_at)->not->toBeNull();

    Bus::assertDispatched(CreatePaymentLinkJob::class);
});

it('queues payment link whatsapp when a recovery link already exists', function () {
    Bus::fake();

    $user = createRecoveryManager();
    $order = createAbandonedOrder([
        'payment_link_id' => 'plink_test',
        'payment_link_url' => 'https://rzp.io/i/test',
    ]);

    actingAs($user)
        ->post(route('admin.donations.recovery.nudge'), [
            'order_ids' => [$order->id],
        ])
        ->assertRedirect(route('admin.donations.recovery'));

    Bus::assertDispatched(SendPaymentLinkWhatsAppJob::class);
    Bus::assertNotDispatched(CreatePaymentLinkJob::class);
});

it('forbids nudging without manage receipts permission', function () {
    $user = createRecoveryViewer();
    $order = createAbandonedOrder();

    actingAs($user)
        ->post(route('admin.donations.recovery.nudge'), [
            'order_ids' => [$order->id],
        ])
        ->assertForbidden();
});
