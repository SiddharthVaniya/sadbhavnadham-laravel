<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Services\RazorpayPaymentLinkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class NotifyPaymentLinkJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int $orderId,
        private string $medium,
    ) {}

    public function handle(RazorpayPaymentLinkService $paymentLinks): void
    {
        $order = DonationOrder::with(['items.causeModel', 'items.package'])->find($this->orderId);
        if (! $order) {
            return;
        }

        try {
            $paymentLinks->notifyOrder($order, $this->medium);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Payment link notify skipped', [
                'order_id' => $order->id,
                'medium' => $this->medium,
                'reason' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Payment link notify job failed', [
                'order_id' => $order->id,
                'medium' => $this->medium,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
