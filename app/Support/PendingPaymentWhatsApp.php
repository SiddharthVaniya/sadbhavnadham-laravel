<?php

namespace App\Support;

use App\Jobs\SendPendingPaymentWhatsAppJob;
use App\Models\DonationOrder;
use App\Models\Setting;

class PendingPaymentWhatsApp
{
    public static function delayMinutes(): int
    {
        return max(1, (int) config('services.aisensy.pending_payment_delay_minutes', 10));
    }

    public static function scheduleForOrder(DonationOrder $order): void
    {
        if (! Setting::isEnabled(Setting::SEND_PENDING_PAYMENT_WHATSAPP)) {
            return;
        }

        if (! $order->isPending()) {
            return;
        }

        SendPendingPaymentWhatsAppJob::dispatch($order->id)
            ->delay(now()->addMinutes(self::delayMinutes()));
    }
}
