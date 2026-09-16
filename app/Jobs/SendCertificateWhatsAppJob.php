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

class SendCertificateWhatsAppJob implements ShouldQueue
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
            if ($this->order->certificate_whatsapp_sent_at) {
                return;
            }

            if (! $donationWhatsAppPolicy->shouldSendCertificate($this->order)) {
                return;
            }
        }

        try {
            $sent = $aiSensyService->sendCertificateWhatsApp($this->order);

            if (! $sent) {
                throw new \RuntimeException('AiSensy certificate WhatsApp was not sent.');
            }

            $this->order->update([
                'certificate_whatsapp_sent_at' => now(),
                'certificate_whatsapp_failed_at' => null,
                'certificate_whatsapp_last_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Certificate WhatsApp job failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->order->forceFill([
            'certificate_whatsapp_failed_at' => now(),
            'certificate_whatsapp_last_error' => $exception?->getMessage() ?? 'Certificate WhatsApp job failed',
        ])->save();
    }
}
