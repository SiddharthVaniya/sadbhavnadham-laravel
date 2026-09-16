<?php

use App\Models\DonationOrder;

it('does not format an empty receipt number with only the prefix', function () {
    $order = new DonationOrder;

    expect($order->receiptNumberFormatted())->toBe('');
});

it('formats an assigned receipt number using the order payment provider', function () {
    $order = new DonationOrder([
        'payment_provider' => DonationOrder::PROVIDER_OFFLINE,
        'receipt_number' => 12,
    ]);

    expect($order->receiptNumberFormatted())->toBe('MSCT-OFF-12');
});

it('formats receipt numbers with provider-specific prefixes', function (string $provider, string $expected) {
    expect(DonationOrder::formatReceiptNumber(548, $provider))->toBe($expected);
})->with([
    'razorpay' => [DonationOrder::PROVIDER_RAZORPAY, 'MSCT-RZP-548'],
    'razorpay_qr' => [DonationOrder::PROVIDER_RAZORPAY_QR, 'MSCT-RZP-548'],
    'offline' => [DonationOrder::PROVIDER_OFFLINE, 'MSCT-OFF-548'],
    'danamojo' => [DonationOrder::PROVIDER_DANAMOJO, 'MSCT-DNMJ-548'],
    'cashfree' => [DonationOrder::PROVIDER_CASHFREE, 'MSCT-CF-548'],
]);

it('maps razorpay qr into the razorpay receipt family', function () {
    expect(DonationOrder::receiptProviderFamily(DonationOrder::PROVIDER_RAZORPAY_QR))
        ->toBe(DonationOrder::PROVIDER_RAZORPAY)
        ->and(DonationOrder::receiptSequenceName(DonationOrder::PROVIDER_RAZORPAY_QR))
        ->toBe('donation_orders_razorpay');
});
