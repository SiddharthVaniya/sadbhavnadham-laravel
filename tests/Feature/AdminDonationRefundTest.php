<?php

use App\Models\DonationOrder;
use App\Models\User;
use App\Services\RazorpayRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function refundAdmin(): User
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

function paidRazorpayOrder(array $overrides = []): DonationOrder
{
    return DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_refund_1',
        'provider_payment_id' => 'pay_refund_1',
        'donor_name' => 'Refund Donor',
        'donor_email' => 'refund@example.com',
        'donor_phone' => '9999999999',
        'currency' => 'INR',
        'total_amount' => 200,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ], $overrides));
}

it('refunds a paid razorpay donation and stores the refund id', function () {
    $this->partialMock(RazorpayRefundService::class, function ($mock) {
        $mock->shouldReceive('requestFullRefund')->once()->andReturn([
            'id' => 'rfnd_test_1',
            'amount' => 20000,
        ]);
    });

    $user = refundAdmin();
    $order = paidRazorpayOrder();

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donation.can_refund', true)
            ->where('donation.status', DonationOrder::STATUS_PAID)
        );

    actingAs($user)
        ->post(route('admin.donations.refund', $order))
        ->assertRedirect(route('admin.donations.show', $order));

    $order->refresh();

    expect($order->status)->toBe(DonationOrder::STATUS_REFUNDED)
        ->and($order->razorpay_refund_id)->toBe('rfnd_test_1')
        ->and((float) $order->refund_amount)->toBe(200.0)
        ->and($order->refunded_at)->not->toBeNull();

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donation.status', DonationOrder::STATUS_REFUNDED)
            ->where('donation.can_refund', false)
            ->where('donation.razorpay_refund_id', 'rfnd_test_1')
            ->where('donation.is_refunded', true)
        );
});

it('rejects a second refund', function () {
    $this->partialMock(RazorpayRefundService::class, function ($mock) {
        $mock->shouldReceive('requestFullRefund')->once()->andReturn([
            'id' => 'rfnd_test_2',
            'amount' => 20000,
        ]);
    });

    $user = refundAdmin();
    $order = paidRazorpayOrder(['provider_payment_id' => 'pay_refund_2']);

    actingAs($user)->post(route('admin.donations.refund', $order))->assertRedirect();

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.refund', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHasErrors('refund');

    expect($order->fresh()->razorpay_refund_id)->toBe('rfnd_test_2');
});

it('does not refund a non-razorpay donation', function () {
    $this->partialMock(RazorpayRefundService::class, function ($mock) {
        $mock->shouldReceive('requestFullRefund')->never();
    });

    $user = refundAdmin();
    $order = paidRazorpayOrder([
        'payment_provider' => DonationOrder::PROVIDER_DANAMOJO,
        'provider_order_id' => 'danamojo-1',
        'provider_payment_id' => 'dm_1',
    ]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.refund', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHasErrors('refund');

    expect($order->fresh()->status)->toBe(DonationOrder::STATUS_PAID);
});

it('marks a paid order refunded from the refund.processed webhook', function () {
    config([
        'payments.razorpay.key' => 'rzp_test_key',
        'payments.razorpay.secret' => 'rzp_test_secret',
        'payments.razorpay.webhook_secret' => 'whsec_test',
    ]);

    $order = paidRazorpayOrder([
        'provider_payment_id' => 'pay_webhook_1',
        'provider_order_id' => 'order_webhook_1',
    ]);

    $payload = [
        'event' => 'refund.processed',
        'payload' => [
            'refund' => [
                'entity' => [
                    'id' => 'rfnd_webhook_1',
                    'payment_id' => 'pay_webhook_1',
                    'amount' => 20000,
                    'currency' => 'INR',
                ],
            ],
        ],
    ];

    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $body, 'whsec_test');

    $this->call(
        'POST',
        '/api/webhook/razorpay',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ],
        $body,
    )->assertOk();

    $order->refresh();

    expect($order->status)->toBe(DonationOrder::STATUS_REFUNDED)
        ->and($order->razorpay_refund_id)->toBe('rfnd_webhook_1')
        ->and((float) $order->refund_amount)->toBe(200.0);
});
