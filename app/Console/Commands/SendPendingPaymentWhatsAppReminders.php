<?php

namespace App\Console\Commands;

use App\Jobs\SendPendingPaymentWhatsAppJob;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Support\PendingPaymentWhatsApp;
use Illuminate\Console\Command;

class SendPendingPaymentWhatsAppReminders extends Command
{
    protected $signature = 'donations:send-pending-payment-whatsapp
                            {--limit=100 : Max pending orders to queue per run}';

    protected $description = 'Queue WhatsApp reminders for Razorpay checkouts still pending after the configured delay';

    public function handle(): int
    {
        if (! Setting::isEnabled(Setting::SEND_PENDING_PAYMENT_WHATSAPP)) {
            $this->info('Pending payment WhatsApp is disabled.');

            return self::SUCCESS;
        }

        $delayMinutes = PendingPaymentWhatsApp::delayMinutes();
        $limit = max(1, (int) $this->option('limit'));

        $orders = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PENDING)
            ->where('payment_provider', DonationOrder::PROVIDER_RAZORPAY)
            ->whereNull('pending_payment_whatsapp_sent_at')
            ->where('created_at', '<=', now()->subMinutes($delayMinutes))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);

        foreach ($orders as $order) {
            SendPendingPaymentWhatsAppJob::dispatch($order->id);
        }

        $this->info(sprintf(
            'Queued %d pending-payment WhatsApp reminder(s) (delay ≥ %d min).',
            $orders->count(),
            $delayMinutes,
        ));

        return self::SUCCESS;
    }
}
