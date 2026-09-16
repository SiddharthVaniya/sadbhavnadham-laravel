<?php

namespace App\Jobs;

use App\Mail\DonationReceiptMail;
use App\Models\DonationOrder;
use App\Support\DonationNotificationRetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDonationReceiptJob implements ShouldQueue
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

    public function handle(): void
    {
        if (! $this->order->isPaid()) {
            return;
        }

        if (! $this->forceSend && $this->order->receipt_sent_at) {
            return;
        }

        if (trim((string) $this->order->donor_email) === '') {
            Log::info('Skipping receipt email; order has no donor email', [
                'order_id' => $this->order->id,
            ]);

            DonationNotificationRetry::markExhausted(
                $this->order,
                DonationNotificationRetry::CHANNEL_RECEIPT,
                'No donor email on order',
            );

            return;
        }

        try {
            Mail::to($this->order->donor_email)->send(new DonationReceiptMail($this->order));
            $this->order->update([
                'receipt_path' => null,
                'receipt_sent_at' => now(),
                'receipt_failed_at' => null,
                'receipt_last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $this->order->update([
                'receipt_failed_at' => now(),
                'receipt_last_error' => $e->getMessage(),
            ]);

            Log::error('Receipt email job failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
