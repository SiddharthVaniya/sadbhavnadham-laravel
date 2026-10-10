<?php

use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Services\DonationPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('updates the donation amount to what Razorpay actually captured', function () {
    Bus::fake();

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_attempt_5001',
        'donor_name' => 'Ajay Dholakia',
        'donor_email' => 'ajayd109@example.com',
        'donor_phone' => '9998989993',
        'currency' => 'INR',
        'total_amount' => 5001,
        'status' => DonationOrder::STATUS_FAILED,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause' => 'old-age-home',
        'title' => 'Custom Donation',
        'quantity' => 1,
        'unit_amount' => 5001,
        'amount' => 5001,
    ]);

    app(DonationPaymentService::class)->handleCaptured([
        'id' => 'pay_captured_5000',
        'order_id' => 'order_paid_5000',
        'amount' => 500000,
        'currency' => 'INR',
        'status' => 'captured',
        'notes' => [
            'donation_order_id' => (string) $order->id,
        ],
    ]);

    $order->refresh();
    $item = $order->items()->first();

    expect($order->isPaid())->toBeTrue()
        ->and((float) $order->total_amount)->toBe(5000.0)
        ->and($order->provider_payment_id)->toBe('pay_captured_5000')
        ->and($order->provider_order_id)->toBe('order_paid_5000')
        ->and((float) $item->amount)->toBe(5000.0)
        ->and((float) $item->unit_amount)->toBe(5000.0);
});
