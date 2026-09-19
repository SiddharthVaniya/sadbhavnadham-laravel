<?php

use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\LogDonationToSheetJob;
use App\Jobs\SendCertificateWhatsAppJob;
use App\Jobs\SendPaymentLinkWhatsAppJob;
use App\Jobs\SendThankYouWhatsAppJob;
use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createReceiptManager(): User
{
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    Permission::firstOrCreate(['name' => 'manage receipts']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['view donations', 'view all donations', 'manage receipts']);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function createPaidDeliveryOrder(array $overrides = []): DonationOrder
{
    return DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'delivery-order-'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Delivery Donor',
        'donor_email' => 'delivery@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 3000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ], $overrides));
}

it('queues google sheet log from donation details', function () {
    Bus::fake();

    $user = createReceiptManager();
    $order = createPaidDeliveryOrder(['sheet_logged_at' => null]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.sheet.resend', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status');

    Bus::assertDispatched(LogDonationToSheetJob::class);
});

it('queues thank-you and certificate whatsapp from donation details', function () {
    Bus::fake();

    $user = createReceiptManager();
    $order = createPaidDeliveryOrder();

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.whatsapp.thank-you', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status');

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.whatsapp.certificate', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status');

    Bus::assertDispatched(SendThankYouWhatsAppJob::class);
    Bus::assertDispatched(SendCertificateWhatsAppJob::class);
});

it('blocks whatsapp resend when donor phone is invalid', function () {
    Bus::fake();

    $user = createReceiptManager();
    $order = createPaidDeliveryOrder([
        'donor_phone' => 'upi-abc123',
    ]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.whatsapp.thank-you', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status', 'Add a valid donor phone on this donation before sending WhatsApp.')
        ->assertSessionHas('flash_tone', 'warning');

    Bus::assertNotDispatched(SendThankYouWhatsAppJob::class);
});

it('exposes delivery action urls on the donation detail page', function () {
    $user = createReceiptManager();
    $order = createPaidDeliveryOrder();

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.can_resend_sheet', true)
            ->where('donation.can_resend_thank_you_whatsapp', true)
            ->where('donation.can_resend_certificate_whatsapp', true)
            ->where('donation.can_resend_payment_link_whatsapp', false)
            ->has('donation.sheet_resend_url')
            ->has('donation.whatsapp_thank_you_url')
            ->has('donation.whatsapp_certificate_url')
            ->has('donation.whatsapp_payment_link_url'));
});

it('queues payment link whatsapp resend for failed donations', function () {
    Bus::fake();

    $user = createReceiptManager();
    $order = createPaidDeliveryOrder([
        'status' => DonationOrder::STATUS_FAILED,
        'paid_at' => null,
        'failed_at' => now(),
        'payment_link_id' => 'plink_test_123',
        'payment_link_url' => 'https://rzp.io/i/test-link',
        'payment_link_sent_at' => now()->subHour(),
    ]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.whatsapp.payment-link', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status');

    Bus::assertDispatched(SendPaymentLinkWhatsAppJob::class);
    Bus::assertNotDispatched(CreatePaymentLinkJob::class);
});

it('queues payment link creation when failed donation has no link yet', function () {
    Bus::fake();

    $user = createReceiptManager();
    $order = createPaidDeliveryOrder([
        'status' => DonationOrder::STATUS_FAILED,
        'paid_at' => null,
        'failed_at' => now(),
        'payment_link_id' => null,
        'payment_link_url' => null,
        'payment_link_sent_at' => null,
    ]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.whatsapp.payment-link', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status');

    Bus::assertDispatched(CreatePaymentLinkJob::class);
    Bus::assertNotDispatched(SendPaymentLinkWhatsAppJob::class);
});

it('marks pending donations failed and queues payment link on send link instant', function () {
    Bus::fake();

    $user = createReceiptManager();
    $order = createPaidDeliveryOrder([
        'status' => DonationOrder::STATUS_PENDING,
        'paid_at' => null,
        'failed_at' => null,
        'payment_link_id' => null,
        'payment_link_url' => null,
        'payment_link_sent_at' => null,
    ]);

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.whatsapp.payment-link', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status');

    $order->refresh();

    expect($order->isFailed())->toBeTrue()
        ->and($order->failed_at)->not->toBeNull();

    Bus::assertDispatched(CreatePaymentLinkJob::class);
    Bus::assertNotDispatched(SendPaymentLinkWhatsAppJob::class);
});

it('exposes send link for pending donation details', function () {
    $user = createReceiptManager();
    $order = createPaidDeliveryOrder([
        'status' => DonationOrder::STATUS_PENDING,
        'paid_at' => null,
        'failed_at' => null,
        'payment_link_url' => null,
        'payment_link_sent_at' => null,
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.is_pending', true)
            ->where('donation.is_failed', false)
            ->where('donation.can_resend_payment_link_whatsapp', true)
            ->where('donation.delivery.payment_link_whatsapp.label', 'Pending · send link'));
});

it('exposes payment link delivery status on failed donation details', function () {
    $user = createReceiptManager();
    $order = createPaidDeliveryOrder([
        'status' => DonationOrder::STATUS_FAILED,
        'paid_at' => null,
        'failed_at' => now(),
        'payment_link_url' => 'https://rzp.io/i/show-link',
        'payment_link_sent_at' => now()->subMinutes(20),
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.is_failed', true)
            ->where('donation.can_resend_payment_link_whatsapp', true)
            ->where('donation.delivery.payment_link_whatsapp.status', 'sent')
            ->where('donation.delivery.payment_link_whatsapp.url', 'https://rzp.io/i/show-link'));
});
