<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Models\Setting;
use App\Services\RazorpayPaymentLinkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatePaymentLinkJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int $orderId,
        private bool $immediate = false,
    ) {}

    public function handle(RazorpayPaymentLinkService $paymentLinks): void
    {
        $order = DonationOrder::with(['items.causeModel', 'items.package'])->find($this->orderId);
        if (! $order) {
            return;
        }

        if (! $order->isFailed()) {
            Log::info('Payment link job skipped', [
                'order_id' => $order->id,
                'reason' => 'not_failed',
                'status' => $order->status,
                'immediate' => $this->immediate,
            ]);

            return;
        }

        if ($order->payment_link_id) {
            Log::info('Payment link job skipped', [
                'order_id' => $order->id,
                'reason' => 'link_already_exists',
                'immediate' => $this->immediate,
                'has_url' => filled($order->payment_link_url),
            ]);

            if ($this->immediate && filled($order->payment_link_url)) {
                $this->dispatchWhatsApp($order);
            }

            return;
        }

        if (! $order->failed_at) {
            return;
        }

        if (! $this->immediate && $order->failed_at->diffInMinutes(now()) < 5) {
            self::dispatch($order->id)->delay(now()->addMinutes(5));

            return;
        }

        try {
            $order = $paymentLinks->createForOrder($order);
        } catch (Throwable $exception) {
            Log::error('Payment link Razorpay create failed', [
                'order_id' => $order->id,
                'immediate' => $this->immediate,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Payment link created', [
            'order_id' => $order->id,
            'payment_link_id' => $order->payment_link_id,
            'immediate' => $this->immediate,
        ]);

        $this->dispatchWhatsApp($order);
    }

    private function dispatchWhatsApp(DonationOrder $order): void
    {
        if (! Setting::isEnabled(Setting::SEND_WHATSAPP_PAYMENT_LINK)) {
            Log::warning('Payment-link WhatsApp not dispatched', [
                'order_id' => $order->id,
                'reason' => 'send_whatsapp_payment_link_disabled',
            ]);

            return;
        }

        if (! filled($order->payment_link_url)) {
            return;
        }

        dispatch(new SendPaymentLinkWhatsAppJob($order->id, $this->immediate));

        Log::info('Payment-link WhatsApp job dispatched', [
            'order_id' => $order->id,
            'force_send' => $this->immediate,
        ]);
    }
}
