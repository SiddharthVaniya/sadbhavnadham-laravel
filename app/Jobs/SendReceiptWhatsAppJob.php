<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Services\AiSensyService;
use App\Services\DonationWhatsAppPolicy;
use App\Support\DonationNotificationRetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReceiptWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private DonationOrder $order, private bool $forceSend = false) {}

    public function tries(): int
    {
        return DonationNotificationRetry::jobTries();
    }

    public function backoff(): int
    {
        return DonationNotificationRetry::jobBackoffSeconds();
    }

    public function handle(AiSensyService $aiSensyService, DonationWhatsAppPolicy $donationWhatsAppPolicy): void
    {
        if (! $this->order->isPaid()) {
            return;
        }

        if (! $this->forceSend) {
            if ($this->order->receipt_whatsapp_sent_at) {
                return;
            }

            if (! $donationWhatsAppPolicy->shouldSendReceipt($this->order)) {
                return;
            }
        }

        try {
            $sent = $aiSensyService->sendReceiptWhatsApp($this->order);

            if (! $sent) {
                throw new \RuntimeException('AiSensy receipt WhatsApp was not sent.');
            }

            $this->order->update([
                'receipt_whatsapp_sent_at' => now(),
                'receipt_whatsapp_failed_at' => null,
                'receipt_whatsapp_last_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Receipt WhatsApp job failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->order->forceFill([
            'receipt_whatsapp_failed_at' => now(),
            'receipt_whatsapp_last_error' => $exception?->getMessage() ?? 'Receipt WhatsApp job failed',
        ])->save();
    }
}
