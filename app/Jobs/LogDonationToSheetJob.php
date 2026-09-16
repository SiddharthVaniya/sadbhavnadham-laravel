<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Models\Setting;
use App\Services\GoogleSheetsLogger;
use App\Support\DonationNotificationRetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogDonationToSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private DonationOrder $order,
        private string $status = 'captured',
        private bool $force = false,
    ) {}

    public function tries(): int
    {
        return DonationNotificationRetry::jobTries();
    }

    public function backoff(): int
    {
        return DonationNotificationRetry::jobBackoffSeconds();
    }

    public function handle(GoogleSheetsLogger $sheetsLogger): void
    {
        $this->order->refresh();

        if ($this->status === 'failed' && ! Setting::isEnabled(Setting::LOG_FAILED_DONATIONS_TO_SHEET, false)) {
            return;
        }

        if ($this->status === 'captured' && ! $this->order->isPaid()) {
            return;
        }

        if ($this->status === 'captured' && $this->order->sheet_logged_at && ! $this->force) {
            return;
        }

        try {
            $sheetsLogger->logDonation($this->order, $this->status);

            if ($this->status === 'captured') {
                DonationOrder::query()
                    ->whereKey($this->order->id)
                    ->update(['sheet_logged_at' => now()]);
            }

        } catch (\Throwable $e) {
            Log::error('Google Sheet job failed', [
                'order_id' => $this->order->id,
                'status' => $this->status,
                'error' => $e->getMessage(),
            ]);

            // Let queue retry
            throw $e;
        }
    }
}
