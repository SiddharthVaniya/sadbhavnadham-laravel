<?php

use App\Support\RazorpayPaymentFailure;

it('builds a short razorpay failure label from reason and source', function (array $payment, string $label) {
    expect(RazorpayPaymentFailure::label(RazorpayPaymentFailure::fromPayment($payment)))->toBe($label);
})->with([
    'invalid card' => [[
        'error_source' => 'customer',
        'error_reason' => 'authentication_failed',
        'error_description' => 'Payment failed because of invalid card details',
    ], 'Customer · Invalid card'],
    'insufficient funds' => [[
        'error_source' => 'customer',
        'error_reason' => 'insufficient_funds',
        'error_description' => 'The customer does not have sufficient funds',
    ], 'Customer · Insufficient funds'],
    'invalid upi id' => [[
        'error_source' => 'customer',
        'error_reason' => 'invalid_vpa',
    ], 'Customer · Invalid UPI ID'],
    'upi request expired' => [[
        'error_source' => 'customer',
        'error_reason' => 'payment_collect_request_expired',
    ], 'Customer · UPI request expired'],
    'netbanking' => [[
        'error_source' => 'customer',
        'error_reason' => 'user_not_registered_for_netbanking',
    ], 'Customer · Netbanking not registered'],
    'bank error' => [[
        'error_source' => 'issuer_bank',
        'error_reason' => 'bank_technical_error',
    ], 'Bank · Bank error'],
    'gateway error' => [[
        'error_source' => 'gateway',
        'error_reason' => 'gateway_technical_error',
    ], 'Gateway · Gateway error'],
    'razorpay server' => [[
        'error_source' => 'internal',
        'error_reason' => 'server_error',
    ], 'Razorpay · Razorpay error'],
    'cancelled' => [[
        'error_source' => 'customer',
        'error_reason' => 'payment_cancelled',
    ], 'Customer · Cancelled by customer'],
    'incorrect otp' => [[
        'error_source' => 'customer',
        'error_reason' => 'incorrect_otp',
    ], 'Customer · Incorrect OTP'],
]);
