<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Services\Meta\MetaConversionsApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMetaCapiPurchaseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $donationOrderId,
    ) {}

    public function handle(MetaConversionsApiService $service): void
    {
        $order = DonationOrder::query()->find($this->donationOrderId);

        if (
            ! $order
            || ! $order->isPaid()
            || $order->isFailed()
            || $order->isRefunded()
            || round((float) $order->total_amount, 2) <= 0
        ) {
            return;
        }

        $service->sendPurchase($order);
    }
}
