<?php

namespace App\Console\Commands;

use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\SendPaymentLinkWhatsAppJob;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Services\DonationWhatsAppPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NudgeStalePendingDonationsCommand extends Command
{
    protected $signature = 'donations:nudge-stale-pending
                            {--minutes=5 : Pending checkouts older than this many minutes}
                            {--max-age-days=7 : Ignore pending older than this many days}
                            {--limit=50 : Max orders to process per run}
                            {--dry-run : List matches without marking failed or queueing jobs}';

    protected $description = 'Mark stale pending checkouts as failed and queue payment-link fail WhatsApp (default after 5 minutes)';

    public function handle(DonationWhatsAppPolicy $donationWhatsAppPolicy): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $maxAgeDays = max(1, (int) $this->option('max-age-days'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $olderThan = now()->subMinutes($minutes);
        $newerThan = now()->subDays($maxAgeDays);

        $orders = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PENDING)
            ->whereNull('paid_at')
            ->whereNotNull('provider_order_id')
            ->whereNull('payment_link_sent_at')
            ->where('created_at', '<=', $olderThan)
            ->where('created_at', '>=', $newerThan)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $converted = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if (! $donationWhatsAppPolicy->hasSendablePhoneNumber($order->donor_phone)) {
                $skipped++;
                Log::info('Stale pending skip: invalid phone', [
                    'order_id' => $order->id,
                    'minutes' => $minutes,
                ]);

                continue;
            }

            if ($dryRun) {
                $this->line("dry-run #{$order->id} {$order->provider_order_id} {$order->donor_name}");
                $converted++;

                continue;
            }

            $order->markAsFailed();
            $order->refresh();

            if (! filled($order->payment_link_url)) {
                CreatePaymentLinkJob::dispatch($order->id, true);
                $job = 'CreatePaymentLinkJob';
            } else {
                SendPaymentLinkWhatsAppJob::dispatch($order->id, true);
                $job = 'SendPaymentLinkWhatsAppJob';
            }

            $converted++;

            Log::info('Stale pending converted to failed', [
                'order_id' => $order->id,
                'provider_order_id' => $order->provider_order_id,
                'age_minutes' => $order->created_at?->diffInMinutes(now()),
                'job' => $job,
                'whatsapp_enabled' => Setting::isEnabled(Setting::SEND_WHATSAPP_PAYMENT_LINK),
            ]);
        }

        $this->info("Processed stale pending: converted={$converted}, skipped={$skipped}, minutes={$minutes}, max_age_days={$maxAgeDays}.");

        return self::SUCCESS;
    }
}
