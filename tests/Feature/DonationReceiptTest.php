<?php

use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\LogDonationToSheetJob;
use App\Jobs\SendDonationReceiptJob;
use App\Jobs\SendPaymentLinkWhatsAppJob;
use App\Mail\DonationReceiptMail;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\DonationPaymentService;
use App\Services\DonationReceiptPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows admin to generate receipt manually for a paid donation order', function () {
    Bus::fake();

    Permission::firstOrCreate(['name' => 'manage receipts']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo(['manage receipts', 'view all donations']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 1000.00,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    Auth::login($user);

    $response = $this->post(route('admin.donations.receipt.generate', $order));

    $response->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(DonationOrder::STATUS_PAID);
    expect($order->receipt_number)->not->toBeNull();

    Bus::assertDispatched(SendDonationReceiptJob::class);
});

it('generates receipt numbers from an atomic sequence without reusing the latest number', function () {
    Bus::fake();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Existing Donor',
        'donor_email' => 'existing@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 1000.00,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => 9,
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Later Existing Donor',
        'donor_email' => 'later-existing@example.com',
        'donor_phone' => '9999999996',
        'total_amount' => 1500.00,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => 10,
    ]);

    $firstOrder = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'First Donor',
        'donor_email' => 'first@example.com',
        'donor_phone' => '9999999998',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $secondOrder = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Second Donor',
        'donor_email' => 'second@example.com',
        'donor_phone' => '9999999997',
        'total_amount' => 700.00,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $service = app(DonationPaymentService::class);

    $service->generateManualReceipt($firstOrder, false);
    $service->generateManualReceipt($secondOrder, false);

    expect((int) $firstOrder->refresh()->receipt_number)->toBe(11)
        ->and((int) $secondOrder->refresh()->receipt_number)->toBe(12);
});

it('queues failed payment sheet logging instead of logging synchronously', function () {
    Bus::fake();

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_failed_sheet',
        'donor_name' => 'Failed Donor',
        'donor_email' => 'failed@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    app(DonationPaymentService::class)->handleFailed([
        'order_id' => 'order_failed_sheet',
    ]);

    expect($order->refresh()->isFailed())->toBeTrue();

    Bus::assertDispatched(CreatePaymentLinkJob::class);
    Bus::assertDispatched(LogDonationToSheetJob::class);
});

it('does not let a stale failed webhook overwrite a captured payment', function () {
    Bus::fake();

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_failed_capture_race',
        'donor_name' => 'Race Donor',
        'donor_email' => 'race@example.com',
        'donor_phone' => '9999999998',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $capturedAfterStaleRead = false;
    $retrievedEvent = 'eloquent.retrieved: '.DonationOrder::class;

    Event::listen($retrievedEvent, function (DonationOrder $retrievedOrder) use ($order, &$capturedAfterStaleRead): void {
        if ($capturedAfterStaleRead || ! $retrievedOrder->is($order)) {
            return;
        }

        $capturedAfterStaleRead = true;

        DonationOrder::whereKey($retrievedOrder->getKey())->update([
            'status' => DonationOrder::STATUS_PAID,
            'provider_payment_id' => 'pay_captured_during_failed_webhook',
            'paid_at' => now(),
        ]);
    });

    try {
        app(DonationPaymentService::class)->handleFailed([
            'order_id' => 'order_failed_capture_race',
        ]);
    } finally {
        Event::forget($retrievedEvent);
    }

    $order->refresh();

    expect($order->isPaid())->toBeTrue()
        ->and($order->provider_payment_id)->toBe('pay_captured_during_failed_webhook')
        ->and($capturedAfterStaleRead)->toBeTrue();

    Bus::assertNotDispatched(CreatePaymentLinkJob::class);
    Bus::assertNotDispatched(LogDonationToSheetJob::class);
});

it('clears failed recovery state when a later captured webhook marks the order paid', function () {
    Bus::fake();

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_failed_then_captured',
        'donor_name' => 'Recovered Donor',
        'donor_email' => 'recovered@example.com',
        'donor_phone' => '9999999998',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    app(DonationPaymentService::class)->handleFailed([
        'order_id' => 'order_failed_then_captured',
    ]);

    expect($order->refresh()->isFailed())->toBeTrue()
        ->and($order->failed_at)->not->toBeNull();

    app(DonationPaymentService::class)->handleCaptured([
        'id' => 'pay_recovered_after_failure',
        'order_id' => 'order_failed_then_captured',
    ]);

    $order->refresh();

    expect($order->isPaid())->toBeTrue()
        ->and($order->provider_payment_id)->toBe('pay_recovered_after_failure')
        ->and($order->failed_at)->toBeNull()
        ->and($order->receipt_number)->not->toBeNull();
});

it('does not create a recovery payment link for an already paid order with stale failure state', function () {
    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_paid_with_stale_failure',
        'donor_name' => 'Paid Donor',
        'donor_email' => 'paid-stale@example.com',
        'donor_phone' => '9999999997',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'failed_at' => now()->subMinutes(10),
    ]);

    (new CreatePaymentLinkJob($order->id))->handle();

    expect($order->refresh()->payment_link_id)->toBeNull()
        ->and($order->payment_link_url)->toBeNull();
});

it('does not send a recovery payment link whatsapp for an already paid order', function () {
    Http::fake();

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_paid_with_recovery_link',
        'donor_name' => 'Paid Link Donor',
        'donor_email' => 'paid-link@example.com',
        'donor_phone' => '9999999996',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'failed_at' => now()->subMinutes(10),
        'payment_link_url' => 'https://rzp.io/i/already-paid',
    ]);

    (new SendPaymentLinkWhatsAppJob($order->id))->handle();

    expect($order->refresh()->payment_link_sent_at)->toBeNull();

    Http::assertNothingSent();
});

it('repairs a paid captured order that is missing its receipt number', function () {
    Bus::fake();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_paid_without_receipt',
        'donor_name' => 'Paid Donor',
        'donor_email' => 'paid@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    app(DonationPaymentService::class)->handleCaptured([
        'id' => 'pay_paid_without_receipt',
        'order_id' => 'order_paid_without_receipt',
    ]);

    $order = DonationOrder::where('provider_order_id', 'order_paid_without_receipt')->first();

    expect($order->receipt_number)->not->toBeNull();

    Bus::assertDispatched(LogDonationToSheetJob::class);
});

it('uses payment notes when captured payment has no provider order id', function () {
    Bus::fake();

    $unrelatedPendingOrder = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Unrelated Donor',
        'donor_email' => 'unrelated@example.com',
        'donor_phone' => '9999999997',
        'total_amount' => 300.00,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $paymentLinkOrder = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_original_failed',
        'donor_name' => 'Payment Link Donor',
        'donor_email' => 'payment-link@example.com',
        'donor_phone' => '9999999998',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now(),
    ]);

    app(DonationPaymentService::class)->handleCaptured([
        'id' => 'pay_payment_link_capture',
        'notes' => [
            'order_id' => $paymentLinkOrder->id,
        ],
    ]);

    expect($paymentLinkOrder->refresh()->isPaid())->toBeTrue()
        ->and($paymentLinkOrder->provider_payment_id)->toBe('pay_payment_link_capture')
        ->and($paymentLinkOrder->receipt_number)->not->toBeNull()
        ->and($unrelatedPendingOrder->refresh()->isPaid())->toBeFalse()
        ->and($unrelatedPendingOrder->provider_payment_id)->toBeNull()
        ->and($unrelatedPendingOrder->receipt_number)->toBeNull();

    Bus::assertDispatched(LogDonationToSheetJob::class);
});

it('sends receipt email without pdf attachment for paid orders', function () {
    Mail::fake();

    Setting::query()->updateOrCreate(
        ['key' => Setting::ATTACH_RECEIPT_PDF],
        ['value' => '0', 'label' => 'Attach PDF', 'group' => 'notifications'],
    );

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Receipt Donor',
        'donor_email' => 'receipt@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => 1,
        'paid_at' => now(),
    ]);

    app(SendDonationReceiptJob::class, [
        'order' => $order,
        'forceSend' => true,
    ])->handle();

    $order->refresh();

    expect($order->receipt_path)->toBeNull();
    expect($order->receipt_sent_at)->not->toBeNull();

    Mail::assertSent(DonationReceiptMail::class, function (DonationReceiptMail $mail) use ($order) {
        return $mail->order->is($order) && $mail->attachments() === [];
    });
});

it('attaches a receipt pdf when the setting is enabled', function () {
    Mail::fake();

    Setting::query()->updateOrCreate(
        ['key' => Setting::ATTACH_RECEIPT_PDF],
        ['value' => '1', 'label' => 'Attach PDF', 'group' => 'notifications'],
    );

    $fakePdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $fakePdf->shouldReceive('output')->zeroOrMoreTimes()->andReturn('%PDF-1.4 fake');

    $this->mock(DonationReceiptPdfService::class, function ($mock) use ($fakePdf) {
        $mock->shouldReceive('make')->once()->andReturn($fakePdf);
    });

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Receipt Donor',
        'donor_email' => 'receipt@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => 42,
        'paid_at' => now(),
    ]);

    app(SendDonationReceiptJob::class, [
        'order' => $order,
        'forceSend' => true,
    ])->handle();

    Mail::assertSent(DonationReceiptMail::class, function (DonationReceiptMail $mail) use ($order) {
        $attachments = $mail->attachments();

        return $mail->order->is($order)
            && count($attachments) === 1
            && $attachments[0]->as === 'donation-receipt-42.pdf';
    });
});

it('redirects admin print to preview when pdf is disabled', function () {
    Permission::firstOrCreate(['name' => 'manage receipts']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo(['manage receipts', 'view all donations']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Print Donor',
        'donor_email' => 'print@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 1000.00,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    Auth::login($user);

    $response = $this->get(route('admin.donations.receipt.print', $order));

    $response->assertRedirect(route('admin.donations.receipt.preview', $order));
});

it('allows admin to record offline donation and queue receipt', function () {
    Bus::fake();

    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'manage donations']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo(['view donations', 'manage donations']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    Auth::login($user);

    $response = $this->post(route('admin.donations.store'), [
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
        'send_receipt_email' => 1,
    ]);

    $response->assertRedirect(route('admin.donations.offline'));

    $order = DonationOrder::where('donor_email', 'offline@example.com')->first();
    expect($order)->not->toBeNull();
    expect($order->receipt_number)->not->toBeNull();

    Bus::assertDispatched(SendDonationReceiptJob::class);
    Bus::assertDispatched(LogDonationToSheetJob::class);
});
