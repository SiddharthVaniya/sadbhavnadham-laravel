<?php

namespace App\Jobs;

use App\Models\Donor;
use App\Services\AiSensyService;
use App\Services\DonationWhatsAppPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendBirthdayWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Donor $donor,
        private ?string $onDate = null,
        private bool $forceSend = false,
    ) {}

    public function handle(AiSensyService $aiSensyService, DonationWhatsAppPolicy $donationWhatsAppPolicy): void
    {
        $date = $this->onDate === null
            ? now()->toDateString()
            : Carbon::parse($this->onDate)->toDateString();

        if (! $donationWhatsAppPolicy->shouldSendBirthday($this->donor->fresh(), $date, $this->forceSend)) {
            return;
        }

        try {
            $sent = $aiSensyService->sendBirthdayWhatsApp($this->donor->fresh());

            if (! $sent) {
                throw new \RuntimeException('AiSensy birthday WhatsApp was not sent.');
            }

            $this->donor->update([
                'birthday_whatsapp_sent_on' => $date,
            ]);
        } catch (\Throwable $e) {
            Log::error('Birthday WhatsApp job failed', [
                'donor_id' => $this->donor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
