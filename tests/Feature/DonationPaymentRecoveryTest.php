<?php

use App\Jobs\CreatePaymentLinkJob;
use App\Models\DonationOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('clears the failed timestamp when a failed donation is later marked paid', function () {
    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_later_paid',
        'donor_name' => 'Paid Donor',
        'donor_email' => 'paid@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now()->subMinutes(10),
    ]);

    $order->markAsPaid('pay_later_paid');

    $order->refresh();

    expect($order->isPaid())->toBeTrue()
        ->and($order->provider_payment_id)->toBe('pay_later_paid')
        ->and($order->failed_at)->toBeNull();
});

it('does not create a recovery payment link for an already paid donation', function () {
    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_paid_with_failed_timestamp',
        'donor_name' => 'Paid Donor',
        'donor_email' => 'paid@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'failed_at' => now()->subMinutes(10),
    ]);

    app(CreatePaymentLinkJob::class, [
        'orderId' => $order->id,
    ])->handle();

    $order->refresh();

    expect($order->payment_link_id)->toBeNull()
        ->and($order->payment_link_url)->toBeNull();
});
