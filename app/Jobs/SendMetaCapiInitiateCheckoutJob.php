<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Services\Meta\MetaConversionsApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMetaCapiInitiateCheckoutJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $donationOrderId,
        public ?string $clientUserAgent = null,
    ) {}

    public function handle(MetaConversionsApiService $service): void
    {
        $order = DonationOrder::query()->find($this->donationOrderId);

        if (! $order) {
            return;
        }

        $service->sendInitiateCheckout($order, $this->clientUserAgent);
    }
}
