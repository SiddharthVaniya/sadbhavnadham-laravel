<?php

use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\NotifyPaymentLinkJob;
use App\Jobs\SendPaymentLinkWhatsAppJob;
use App\Models\DonationOrder;
use App\Models\User;
use App\Services\RazorpayPaymentLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createPaymentLinkNotifyManager(): User
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

function createFailedNotifyOrder(array $overrides = []): DonationOrder
{
    return DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_notify_'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Notify Donor',
        'donor_email' => 'notify@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now(),
        'payment_link_id' => 'plink_notify_test',
        'payment_link_url' => 'https://rzp.io/i/notify-test',
    ], $overrides));
}

function createAbandonedNotifyOrder(array $overrides = []): DonationOrder
{
    $createdAt = $overrides['created_at'] ?? now()->subMinutes(20);
    unset($overrides['created_at'], $overrides['updated_at']);

    $order = createFailedNotifyOrder($overrides);

    $order->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->save();

    return $order->fresh();
}

it('queues payment link email notify for failed donations', function () {
    Bus::fake();

    $user = createPaymentLinkNotifyManager();
    $order = createFailedNotifyOrder();

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.payment-link.notify', [$order, 'email']))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status');

    Bus::assertDispatched(NotifyPaymentLinkJob::class, function (NotifyPaymentLinkJob $job) use ($order): bool {
        return (fn () => $this->orderId === $order->id && $this->medium === 'email')
            ->call($job);
    });
    Bus::assertNotDispatched(CreatePaymentLinkJob::class);
    Bus::assertNotDispatched(SendPaymentLinkWhatsAppJob::class);
});

it('queues payment link sms notify for failed donations', function () {
    Bus::fake();

    $user = createPaymentLinkNotifyManager();
    $order = createFailedNotifyOrder();

    actingAs($user)
        ->post(route('admin.donations.payment-link.notify', [$order, 'sms']))
        ->assertRedirect(route('admin.donations.show', $order));

    Bus::assertDispatched(NotifyPaymentLinkJob::class, function (NotifyPaymentLinkJob $job) use ($order): bool {
        return (fn () => $this->orderId === $order->id && $this->medium === 'sms')
            ->call($job);
    });
});

it('blocks email notify when donor email is missing', function () {
    Bus::fake();

    $user = createPaymentLinkNotifyManager();
    $order = createFailedNotifyOrder([
        'donor_email' => '',
    ]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.payment-link.notify', [$order, 'email']))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('flash_tone', 'warning');

    Bus::assertNotDispatched(NotifyPaymentLinkJob::class);
});

it('blocks sms notify when donor phone is invalid', function () {
    Bus::fake();

    $user = createPaymentLinkNotifyManager();
    $order = createFailedNotifyOrder([
        'donor_phone' => 'upi-abc123',
    ]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.payment-link.notify', [$order, 'sms']))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('flash_tone', 'warning');

    Bus::assertNotDispatched(NotifyPaymentLinkJob::class);
});

it('blocks payment link notify for paid donations', function () {
    Bus::fake();

    $user = createPaymentLinkNotifyManager();
    $order = createFailedNotifyOrder([
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'failed_at' => null,
    ]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.payment-link.notify', [$order, 'email']))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('flash_tone', 'warning');

    Bus::assertNotDispatched(NotifyPaymentLinkJob::class);
});

it('exposes payment link email and sms delivery props on failed donation details', function () {
    $user = createPaymentLinkNotifyManager();
    $order = createFailedNotifyOrder([
        'payment_link_email_sent_at' => now()->subMinutes(10),
        'payment_link_sms_sent_at' => now()->subMinutes(5),
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.can_notify_payment_link_email', true)
            ->where('donation.can_notify_payment_link_sms', true)
            ->where('donation.delivery.payment_link_email.status', 'sent')
            ->where('donation.delivery.payment_link_sms.status', 'sent')
            ->has('donation.payment_link_notify_email_url')
            ->has('donation.payment_link_notify_sms_url'));
});

it('queues email notify from checkout recovery without whatsapp jobs', function () {
    Bus::fake();

    $user = createPaymentLinkNotifyManager();
    $order = createAbandonedNotifyOrder([
        'payment_link_id' => 'plink_recovery_email',
        'payment_link_url' => 'https://rzp.io/i/recovery-email',
    ]);

    actingAs($user)
        ->post(route('admin.donations.recovery.nudge'), [
            'order_ids' => [$order->id],
            'channel' => 'email',
        ])
        ->assertRedirect(route('admin.donations.recovery'));

    Bus::assertDispatched(NotifyPaymentLinkJob::class, function (NotifyPaymentLinkJob $job) use ($order): bool {
        return (fn () => $this->orderId === $order->id && $this->medium === 'email')
            ->call($job);
    });
    Bus::assertNotDispatched(CreatePaymentLinkJob::class);
    Bus::assertNotDispatched(SendPaymentLinkWhatsAppJob::class);
});

it('queues sms notify from checkout recovery', function () {
    Bus::fake();

    $user = createPaymentLinkNotifyManager();
    $order = createAbandonedNotifyOrder();

    actingAs($user)
        ->post(route('admin.donations.recovery.nudge'), [
            'order_ids' => [$order->id],
            'channel' => 'sms',
        ])
        ->assertRedirect(route('admin.donations.recovery'));

    Bus::assertDispatched(NotifyPaymentLinkJob::class, function (NotifyPaymentLinkJob $job) use ($order): bool {
        return (fn () => $this->orderId === $order->id && $this->medium === 'sms')
            ->call($job);
    });
});

it('defaults recovery nudge channel to whatsapp', function () {
    Bus::fake();

    $user = createPaymentLinkNotifyManager();
    $order = createAbandonedNotifyOrder([
        'payment_link_id' => 'plink_default_wa',
        'payment_link_url' => 'https://rzp.io/i/default-wa',
    ]);

    actingAs($user)
        ->post(route('admin.donations.recovery.nudge'), [
            'order_ids' => [$order->id],
        ])
        ->assertRedirect(route('admin.donations.recovery'));

    Bus::assertDispatched(SendPaymentLinkWhatsAppJob::class);
    Bus::assertNotDispatched(NotifyPaymentLinkJob::class);
});

it('creates a missing payment link then notifies without dispatching whatsapp', function () {
    Bus::fake();

    $order = createFailedNotifyOrder([
        'payment_link_id' => null,
        'payment_link_url' => null,
    ]);

    $service = Mockery::mock(RazorpayPaymentLinkService::class);
    $service->shouldReceive('createForOrder')
        ->once()
        ->andReturnUsing(function (DonationOrder $donationOrder) {
            $donationOrder->forceFill([
                'payment_link_id' => 'plink_created',
                'payment_link_url' => 'https://rzp.io/i/created',
            ])->save();

            return $donationOrder->fresh();
        });
    $service->shouldReceive('notify')
        ->once()
        ->withArgs(function (DonationOrder $donationOrder, string $medium): bool {
            return $donationOrder->payment_link_id === 'plink_created' && $medium === 'email';
        });

    (new NotifyPaymentLinkJob($order->id, 'email'))->handle($service);

    Bus::assertNotDispatched(SendPaymentLinkWhatsAppJob::class);
    Bus::assertNotDispatched(CreatePaymentLinkJob::class);
});
