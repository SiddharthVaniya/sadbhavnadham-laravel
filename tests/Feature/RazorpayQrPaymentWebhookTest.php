<?php

use App\Jobs\LogDonationToSheetJob;
use App\Jobs\SendCertificateWhatsAppJob;
use App\Jobs\SendDonationReceiptJob;
use App\Jobs\SendThankYouWhatsAppJob;
use App\Models\DonationOrder;
use App\Models\PaymentEvent;
use App\Models\Setting;
use App\Services\DonationPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Setting::updateOrCreate(['key' => Setting::SEND_RECEIPT_EMAIL], ['value' => '0']);
    Setting::updateOrCreate(['key' => Setting::SEND_WHATSAPP_THANK_YOU], ['value' => '0']);
});

function razorpayQrPaymentPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'pay_qr_test_001',
        'entity' => 'payment',
        'amount' => 50000,
        'currency' => 'INR',
        'status' => 'captured',
        'order_id' => null,
        'method' => 'upi',
        'vpa' => 'donor@okaxis',
        'email' => null,
        'contact' => null,
        'qr_code_id' => 'qr_multi_use_001',
        'created_at' => now()->timestamp,
        'notes' => [],
    ], $overrides);
}

it('auto-creates a paid donation from an unmatched razorpay qr payment', function () {
    app(DonationPaymentService::class)->handleCaptured(razorpayQrPaymentPayload());

    $order = DonationOrder::query()->where('provider_payment_id', 'pay_qr_test_001')->first();

    expect($order)->not->toBeNull()
        ->and($order->payment_provider)->toBe(DonationOrder::PROVIDER_RAZORPAY_QR)
        ->and($order->source_channel)->toBe(\App\Services\DonationAttributionService::CHANNEL_RAZORPAY_QR)
        ->and($order->provider_order_id)->toBe('qr-pay_qr_test_001')
        ->and((float) $order->total_amount)->toBe(500.0)
        ->and($order->isPaid())->toBeTrue()
        ->and($order->hasReceipt())->toBeTrue()
        ->and($order->donor_name)->toBe('Unknown Donor')
        ->and($order->donor_phone)->toStartWith('upi-');

    expect(PaymentEvent::query()->where('provider_payment_id', 'pay_qr_test_001')->exists())->toBeTrue();

    Bus::assertDispatched(LogDonationToSheetJob::class);
});

it('uses upi contact as phone when razorpay provides it', function () {
    app(DonationPaymentService::class)->handleCaptured(razorpayQrPaymentPayload([
        'id' => 'pay_qr_with_phone',
        'contact' => '+919876543210',
        'email' => 'donor@example.com',
        'notes' => ['donor_name' => 'QR Donor'],
    ]));

    $order = DonationOrder::query()->where('provider_payment_id', 'pay_qr_with_phone')->first();

    expect($order)->not->toBeNull()
        ->and($order->donor_name)->toBe('QR Donor')
        ->and($order->donor_phone)->toBe('9876543210')
        ->and($order->donor_email)->toBe('donor@example.com');
});

it('does not create duplicate donations for the same qr payment id', function () {
    $service = app(DonationPaymentService::class);
    $payload = razorpayQrPaymentPayload(['id' => 'pay_qr_dup']);

    $service->handleCaptured($payload);
    $service->handleCaptured($payload);

    expect(DonationOrder::query()->where('provider_payment_id', 'pay_qr_dup')->count())->toBe(1);
});

it('skips unmatched payments that are not qr payments', function () {
    app(DonationPaymentService::class)->handleCaptured([
        'id' => 'pay_orphan_checkout',
        'amount' => 10000,
        'currency' => 'INR',
        'status' => 'captured',
        'order_id' => null,
        'method' => 'upi',
    ]);

    expect(DonationOrder::query()->where('provider_payment_id', 'pay_orphan_checkout')->exists())->toBeFalse();
});

it('never sends automatic email or whatsapp for qr donations even when settings are enabled', function () {
    Setting::updateOrCreate(['key' => Setting::SEND_RECEIPT_EMAIL], ['value' => '1']);
    Setting::updateOrCreate(['key' => Setting::SEND_WHATSAPP_THANK_YOU], ['value' => '1']);
    Setting::updateOrCreate(['key' => Setting::SEND_DONATION_CERTIFICATE], ['value' => '1']);

    app(DonationPaymentService::class)->handleCaptured(razorpayQrPaymentPayload([
        'id' => 'pay_qr_no_notify',
        'contact' => '+919876543210',
        'email' => 'donor@example.com',
    ]));

    expect(DonationOrder::query()->where('provider_payment_id', 'pay_qr_no_notify')->exists())->toBeTrue();

    Bus::assertDispatched(LogDonationToSheetJob::class);
    Bus::assertNotDispatched(SendDonationReceiptJob::class);
    Bus::assertNotDispatched(SendThankYouWhatsAppJob::class);
    Bus::assertNotDispatched(SendCertificateWhatsAppJob::class);
});

it('stores paid_at converted to the app timezone', function () {
    $timestamp = 1784376669; // 2026-07-18 12:11:09 UTC => 17:41:09 IST

    app(DonationPaymentService::class)->handleCaptured(razorpayQrPaymentPayload([
        'id' => 'pay_qr_tz',
        'created_at' => $timestamp,
    ]));

    $order = DonationOrder::query()->where('provider_payment_id', 'pay_qr_tz')->first();

    expect($order->paid_at->format('Y-m-d H:i:s'))
        ->toBe(\Illuminate\Support\Carbon::createFromTimestamp($timestamp)
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:s'));
});

it('skips receipt email job when the order has no donor email', function () {
    Mail::fake();

    $order = DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_no_email',
        'provider_payment_id' => 'pay_no_email',
        'donor_name' => 'No Email Donor',
        'donor_email' => '',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    (new SendDonationReceiptJob($order))->handle();

    Mail::assertNothingSent();
    expect($order->fresh()->receipt_failed_at)->not->toBeNull()
        ->and($order->fresh()->receipt_last_error)->toBe('No donor email on order');
});

it('respects configured razorpay qr id allow-list', function () {
    config(['payments.razorpay.qr_code_ids' => ['qr_allowed_only']]);

    app(DonationPaymentService::class)->handleCaptured(razorpayQrPaymentPayload([
        'id' => 'pay_qr_blocked',
        'qr_code_id' => 'qr_other',
    ]));

    expect(DonationOrder::query()->where('provider_payment_id', 'pay_qr_blocked')->exists())->toBeFalse();

    app(DonationPaymentService::class)->handleCaptured(razorpayQrPaymentPayload([
        'id' => 'pay_qr_allowed',
        'qr_code_id' => 'qr_allowed_only',
    ]));

    expect(DonationOrder::query()->where('provider_payment_id', 'pay_qr_allowed')->exists())->toBeTrue();
});

it('lists razorpay qr donations on the offline donations page', function () {
    $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view donations']);
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user = \App\Models\User::factory()->create();
    $user->assignRole('super_admin');

    app(DonationPaymentService::class)->handleCaptured(razorpayQrPaymentPayload([
        'id' => 'pay_qr_list',
    ]));

    $this->actingAs($user)
        ->get(route('admin.donations.offline'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Offline')
            ->has('donations.data', 1)
            ->where('donations.data.0.donor_name', 'Unknown Donor'));
});
