<?php

namespace App\Services;

use App\Models\DonationOrder;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;

class RazorpayRefundService
{
    public function refund(DonationOrder $order): DonationOrder
    {
        if (! $this->canRefund($order)) {
            throw ValidationException::withMessages([
                'refund' => 'Only a paid Razorpay donation with a payment id can be refunded.',
            ]);
        }

        $refund = $this->requestFullRefund((string) $order->provider_payment_id);
        $amountPaise = $refund['amount'] ?? null;
        $amount = is_numeric($amountPaise)
            ? round(((int) $amountPaise) / 100, 2)
            : (float) $order->total_amount;

        $order->markAsRefunded(
            isset($refund['id']) ? (string) $refund['id'] : null,
            $amount,
        );

        return $order->fresh();
    }

    public function canRefund(DonationOrder $order): bool
    {
        if (! $order->isPaid()) {
            return false;
        }

        if (! in_array($order->payment_provider, [
            DonationOrder::PROVIDER_RAZORPAY,
            DonationOrder::PROVIDER_RAZORPAY_QR,
        ], true)) {
            return false;
        }

        $paymentId = (string) $order->provider_payment_id;

        return str_starts_with($paymentId, 'pay_');
    }

    /**
     * @return array{id?: string, amount?: int}
     */
    public function requestFullRefund(string $paymentId): array
    {
        $api = new Api(
            (string) config('payments.razorpay.key'),
            (string) config('payments.razorpay.secret'),
        );

        $refund = $api->payment->fetch($paymentId)->refund();

        return [
            'id' => isset($refund->id) ? (string) $refund->id : null,
            'amount' => isset($refund->amount) ? (int) $refund->amount : null,
        ];
    }

    public function markRefundedFromWebhook(string $paymentId, ?string $refundId, ?int $amountPaise): bool
    {
        $order = DonationOrder::query()
            ->where('provider_payment_id', $paymentId)
            ->first();

        if ($order === null || $order->isRefunded()) {
            return false;
        }

        if (! $order->isPaid()) {
            return false;
        }

        $amount = $amountPaise !== null
            ? round($amountPaise / 100, 2)
            : (float) $order->total_amount;

        $order->markAsRefunded($refundId, $amount);

        return true;
    }
}
