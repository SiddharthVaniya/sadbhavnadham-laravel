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

class SendThankYouWhatsAppJob implements ShouldQueue
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
            if ($this->order->whatsapp_sent_at) {
                return;
            }

            if (! $donationWhatsAppPolicy->shouldSendThankYou($this->order)) {
                return;
            }
        }

        try {
            $sent = $aiSensyService->sendThankYouWhatsApp($this->order);

            if (! $sent) {
                throw new \RuntimeException('AiSensy thank-you WhatsApp was not sent.');
            }

            $this->order->update([
                'whatsapp_sent_at' => now(),
                'whatsapp_failed_at' => null,
                'whatsapp_last_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp job failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->order->forceFill([
            'whatsapp_failed_at' => now(),
            'whatsapp_last_error' => $exception?->getMessage() ?? 'WhatsApp job failed',
        ])->save();
    }
}
