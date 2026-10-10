<?php

use App\Jobs\UpdateDonationOnSheetJob;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use App\Services\DonationPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function syncAmountAdmin(): User
{
    Permission::firstOrCreate(['name' => 'view donations', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view all donations', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit donations', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage donations', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo([
        'view donations',
        'view all donations',
        'edit donations',
        'manage donations',
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function syncAmountOrder(array $overrides = []): DonationOrder
{
    $order = DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_sync_1',
        'provider_payment_id' => 'pay_sync_1',
        'donor_name' => 'Sync Donor',
        'donor_email' => 'sync@example.com',
        'donor_phone' => '9999999999',
        'currency' => 'INR',
        'total_amount' => 5001,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ], $overrides));

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause' => 'old-age-home',
        'title' => 'Custom Donation',
        'quantity' => 1,
        'unit_amount' => (float) $order->total_amount,
        'amount' => (float) $order->total_amount,
    ]);

    return $order;
}

it('shows the sync amount button for razorpay donations with a payment id', function () {
    $user = syncAmountAdmin();
    $order = syncAmountOrder();

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donation.can_sync_amount', true)
            ->where('donation.sync_amount_url', route('admin.donations.sync-amount', $order))
            ->where('donation.total_amount', 5001));
});

it('syncs only the amount from razorpay', function () {
    Bus::fake([UpdateDonationOnSheetJob::class]);

    $this->partialMock(DonationPaymentService::class, function ($mock) {
        $mock->makePartial();
        $mock->shouldReceive('fetchRazorpayPayment')->once()->with('pay_sync_1')->andReturn([
            'id' => 'pay_sync_1',
            'amount' => 500000,
            'order_id' => 'order_paid_5000',
            'status' => 'captured',
        ]);
    });

    $user = syncAmountAdmin();
    $order = syncAmountOrder();

    actingAs($user)
        ->post(route('admin.donations.sync-amount', $order))
        ->assertRedirect(route('admin.donations.show', $order));

    $order->refresh();
    $item = $order->items()->first();

    expect((float) $order->total_amount)->toBe(5000.0)
        ->and($order->provider_order_id)->toBe('order_sync_1')
        ->and((float) $item->amount)->toBe(5000.0);

    Bus::assertDispatched(UpdateDonationOnSheetJob::class);
});

it('rejects sync when there is no razorpay payment id', function () {
    $this->partialMock(DonationPaymentService::class, function ($mock) {
        $mock->makePartial();
        $mock->shouldReceive('fetchRazorpayPayment')->never();
    });

    $user = syncAmountAdmin();
    $order = syncAmountOrder([
        'payment_provider' => DonationOrder::PROVIDER_DANAMOJO,
        'provider_payment_id' => 'dm_1',
    ]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.sync-amount', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHasErrors('sync_amount');
});
