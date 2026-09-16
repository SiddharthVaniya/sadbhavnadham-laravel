<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Services\AiSensyService;
use App\Services\DonationWhatsAppPolicy;
use App\Support\DonationNotificationRetry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPendingPaymentWhatsAppJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int $orderId,
        private bool $forceSend = false,
    ) {}

    public function tries(): int
    {
        return DonationNotificationRetry::jobTries();
    }

    public function backoff(): int
    {
        return DonationNotificationRetry::jobBackoffSeconds();
    }

    public function handle(AiSensyService $aiSensyService, DonationWhatsAppPolicy $policy): void
    {
        $order = DonationOrder::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        if (! $order->isPending()) {
            return;
        }

        if (! $this->forceSend && $order->pending_payment_whatsapp_sent_at) {
            return;
        }

        $delayMinutes = max(1, (int) config('services.aisensy.pending_payment_delay_minutes', 10));

        if (! $this->forceSend && $order->created_at) {
            $elapsedMinutes = (int) $order->created_at->diffInMinutes(now());
            if ($elapsedMinutes < $delayMinutes) {
                self::dispatch($order->id)->delay(now()->addMinutes($delayMinutes - $elapsedMinutes));

                return;
            }
        }

        if (! $this->forceSend && ! $policy->shouldSendPendingPayment($order)) {
            return;
        }

        if ($this->forceSend && ! $policy->hasSendablePhoneNumber($order->donor_phone)) {
            Log::warning('Pending payment WhatsApp force-send skipped: invalid phone', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $sent = $aiSensyService->sendPendingPaymentWhatsApp($order);

        if (! $sent) {
            throw new \RuntimeException('AiSensy pending payment WhatsApp was not sent.');
        }

        $order->update([
            'pending_payment_whatsapp_sent_at' => now(),
        ]);
    }
}
