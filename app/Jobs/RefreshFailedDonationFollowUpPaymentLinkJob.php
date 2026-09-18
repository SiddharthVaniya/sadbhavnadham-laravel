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

class RefreshFailedDonationFollowUpPaymentLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $orderId,
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
        if (! $logger->isConfigured()) {
            return;
        }

        $order = DonationOrder::query()->find($this->orderId);
        if (! $order || ! filled($order->payment_link_url)) {
            return;
        }

        try {
            $logger->refreshPaymentLink($order);
        } catch (\Throwable $e) {
            Log::error('Failed follow-up payment link refresh failed', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
