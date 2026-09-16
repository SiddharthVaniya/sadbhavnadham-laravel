<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Services\AiSensyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPaymentLinkWhatsAppJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int $orderId,
        private bool $forceSend = false,
    ) {}

    public function handle(): void
    {
        /** @var \App\Models\DonationOrder|null $order */
        $order = DonationOrder::find($this->orderId);

        if (! $order) {
            return;
        }

        if (! $order->isFailed()) {
            return;
        }

        if (! $this->forceSend && $order->payment_link_sent_at) {
            return;
        }

        if (! $order->payment_link_url) {
            return;
        }

        $sent = app(AiSensyService::class)
            ->sendPaymentLinkWhatsApp($order);

        if (! $sent) {
            throw new \RuntimeException('AiSensy payment link WhatsApp was not sent.');
        }

        $order->update([
            'payment_link_sent_at' => now(),
        ]);
    }
}
