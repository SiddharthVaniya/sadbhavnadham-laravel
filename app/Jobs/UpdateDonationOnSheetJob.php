<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Services\GoogleSheetsLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateDonationOnSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private DonationOrder $order, private string $status = 'captured') {}

    public function handle(GoogleSheetsLogger $sheetsLogger): void
    {
        $this->order->refresh();
        $this->order->loadMissing('items.causeModel');

        if ($this->status === 'captured' && ! $this->order->isPaid()) {
            return;
        }

        try {
            $sheetsLogger->updateDonationRow($this->order, $this->status);

            if ($this->status === 'captured' && ! $this->order->sheet_logged_at) {
                DonationOrder::query()
                    ->whereKey($this->order->id)
                    ->whereNull('sheet_logged_at')
                    ->update(['sheet_logged_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::error('Google Sheet update job failed', [
                'order_id' => $this->order->id,
                'status' => $this->status,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
