<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Services\FailedDonationFollowUpSheetLogger;
use App\Support\DonationNotificationRetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogFailedDonationFollowUpSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private DonationOrder $order,
    ) {}

    public function tries(): int
    {
        return DonationNotificationRetry::jobTries();
    }

    public function backoff(): int
    {
        return DonationNotificationRetry::jobBackoffSeconds();
    }

    public function handle(FailedDonationFollowUpSheetLogger $logger): void
    {
        $this->order->refresh();

        if (! $logger->isConfigured()) {
            return;
        }

        if (! $this->order->isFailed()) {
            Log::info('Failed follow-up sheet job skipped', [
                'order_id' => $this->order->id,
                'reason' => 'not_failed',
            ]);

            return;
        }

        if ($this->order->failed_sheet_logged_at) {
            return;
        }

        try {
            $logger->log($this->order);

            DonationOrder::query()
                ->whereKey($this->order->id)
                ->update(['failed_sheet_logged_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Failed follow-up sheet job failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
