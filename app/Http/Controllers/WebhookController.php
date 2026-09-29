<?php

namespace App\Http\Controllers;

use App\Services\DonationPaymentService;
use App\Services\DonationSubscriptionWebhookService;
use App\Services\RazorpayRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class WebhookController extends Controller
{
    public function __construct(
        private DonationPaymentService $donationPaymentService,
        private DonationSubscriptionWebhookService $subscriptionWebhookService,
        private RazorpayRefundService $razorpayRefundService,
    ) {}

    /**
     * Razorpay Webhook Entry Point
     */
    public function razorpay(Request $request): JsonResponse
    {
        try {
            $this->verifyWebhookSignature($request);

            $payload = json_decode($request->getContent(), true);
            $event = $payload['event'] ?? null;

            if (! is_string($event) || $event === '') {
                Log::warning('Razorpay webhook without event name', $payload ?? []);

                return response()->json(['status' => 'ignored'], 200);
            }

            if ($event === 'refund.processed') {
                $this->handleRefundProcessed(is_array($payload) ? $payload : []);

                return response()->json(['status' => 'ok'], 200);
            }

            if (str_starts_with($event, 'subscription.')) {
                $this->subscriptionWebhookService->handle($event, $payload);

                return response()->json(['status' => 'ok'], 200);
            }

            $payment = $payload['payload']['payment']['entity'] ?? null;
            $qrCode = $payload['payload']['qr_code']['entity'] ?? null;
            $qrCodeId = is_array($qrCode) ? ($qrCode['id'] ?? null) : null;

            if ($event === 'qr_code.credited') {
                if (! is_array($payment)) {
                    Log::warning('Razorpay qr_code.credited without payment entity', [
                        'event' => $event,
                        'qr_code_id' => $qrCodeId,
                    ]);

                    return response()->json(['status' => 'ignored'], 200);
                }

                if (is_string($qrCodeId) && $qrCodeId !== '' && empty($payment['qr_code_id'])) {
                    $payment['qr_code_id'] = $qrCodeId;
                }

                $this->donationPaymentService->handleCaptured($payment, is_string($qrCodeId) ? $qrCodeId : null);

                return response()->json(['status' => 'ok'], 200);
            }

            if (! is_array($payment)) {
                Log::warning('Razorpay webhook without payment entity', [
                    'event' => $event,
                ]);

                return response()->json(['status' => 'ignored'], 200);
            }

            if (! empty($payment['subscription_id'])) {
                match ($event) {
                    'payment.captured' => $this->subscriptionWebhookService->handleSubscriptionPayment($payment),
                    'payment.failed' => $this->subscriptionWebhookService->handleSubscriptionPaymentFailed($payment),
                    default => Log::info('Unhandled Razorpay subscription payment webhook', [
                        'event' => $event,
                        'payment_id' => $payment['id'] ?? null,
                    ]),
                };

                return response()->json(['status' => 'ok'], 200);
            }

            match ($event) {
                'payment.captured' => $this->donationPaymentService->handleCaptured(
                    $payment,
                    is_string($payment['qr_code_id'] ?? null) ? $payment['qr_code_id'] : null
                ),
                'payment.failed' => $this->donationPaymentService->handleFailed($payment),
                default => Log::info('Unhandled Razorpay webhook event', [
                    'event' => $event,
                ]),
            };

            return response()->json(['status' => 'ok'], 200);

        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            Log::error('Razorpay signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature',
            ], 403);

        } catch (\Throwable $e) {
            Log::error('Razorpay webhook error', [
                'exception' => $e,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleRefundProcessed(array $payload): void
    {
        $refund = $payload['payload']['refund']['entity'] ?? null;
        $payment = $payload['payload']['payment']['entity'] ?? null;

        $paymentId = is_array($refund) ? ($refund['payment_id'] ?? null) : null;

        if (! is_string($paymentId) || $paymentId === '') {
            $paymentId = is_array($payment) ? ($payment['id'] ?? null) : null;
        }

        if (! is_string($paymentId) || $paymentId === '') {
            Log::warning('Razorpay refund.processed without payment id');

            return;
        }

        $refundId = is_array($refund) && is_string($refund['id'] ?? null) ? $refund['id'] : null;
        $amountPaise = is_array($refund) && is_numeric($refund['amount'] ?? null)
            ? (int) $refund['amount']
            : null;

        $updated = $this->razorpayRefundService->markRefundedFromWebhook($paymentId, $refundId, $amountPaise);

        if (! $updated) {
            Log::info('Razorpay refund.processed did not update an order', [
                'payment_id' => $paymentId,
                'refund_id' => $refundId,
            ]);
        }
    }

    /**
     * Verify Razorpay webhook signature
     */
    private function verifyWebhookSignature(Request $request): void
    {
        $webhookSecret = config('payments.razorpay.webhook_secret');

        if (empty($webhookSecret)) {
            throw new \Razorpay\Api\Errors\SignatureVerificationError(
                'Webhook secret is not configured'
            );
        }

        $api = new Api(
            config('payments.razorpay.key'),
            config('payments.razorpay.secret')
        );

        $api->utility->verifyWebhookSignature(
            $request->getContent(),
            $request->header('X-Razorpay-Signature'),
            $webhookSecret
        );
    }
}
