<?php

namespace App\Jobs;

use App\Models\WhatsappCampaignRecipient;
use App\Models\WhatsappCampaignRun;
use App\Support\DonationNotificationRetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsappCampaignRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId) {}

    public function tries(): int
    {
        return DonationNotificationRetry::jobTries();
    }

    public function backoff(): int
    {
        return DonationNotificationRetry::jobBackoffSeconds();
    }

    public function handle(): void
    {
        $run = WhatsappCampaignRun::query()->find($this->runId);

        if ($run === null) {
            return;
        }

        if ($run->status === WhatsappCampaignRun::STATUS_CANCELLED) {
            return;
        }

        $run->forceFill([
            'status' => WhatsappCampaignRun::STATUS_RUNNING,
            'started_at' => $run->started_at ?? now(),
        ])->save();

        $rate = max(1, (int) config('services.aisensy.campaign_rate_per_minute', 60));
        $delaySeconds = (int) max(1, (int) floor(60 / $rate));
        $offset = 0;

        WhatsappCampaignRecipient::query()
            ->where('whatsapp_campaign_run_id', $run->id)
            ->where('status', WhatsappCampaignRecipient::STATUS_PENDING)
            ->orderBy('id')
            ->chunkById(100, function ($recipients) use ($run, &$offset, $delaySeconds): void {
                $run->refresh();

                if ($run->status === WhatsappCampaignRun::STATUS_CANCELLED) {
                    return;
                }

                foreach ($recipients as $recipient) {
                    SendWhatsappCampaignRecipientJob::dispatch($recipient->id)
                        ->delay(now()->addSeconds($offset * $delaySeconds));
                    $offset++;
                }
            });

        if ($offset === 0) {
            $run->forceFill([
                'status' => WhatsappCampaignRun::STATUS_COMPLETED,
                'finished_at' => now(),
            ])->save();
        }
    }
}
