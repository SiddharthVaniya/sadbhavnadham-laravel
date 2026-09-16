<?php

namespace App\Services;

use Razorpay\Api\Api;

class RazorpayPaymentVerifier
{
    /**
     * @throws \Razorpay\Api\Errors\SignatureVerificationError
     */
    public function verifyCheckoutSignature(
        string $razorpayOrderId,
        string $razorpayPaymentId,
        string $razorpaySignature,
    ): void {
        $api = new Api(
            config('payments.razorpay.key'),
            config('payments.razorpay.secret'),
        );

        $api->utility->verifyPaymentSignature([
            'razorpay_order_id' => $razorpayOrderId,
            'razorpay_payment_id' => $razorpayPaymentId,
            'razorpay_signature' => $razorpaySignature,
        ]);
    }
}
