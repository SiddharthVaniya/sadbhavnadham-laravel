<?php

namespace App\Console\Commands;

use App\Models\DonationOrder;
use App\Models\LinkTrackingVisit;
use App\Services\LinkTrackingService;
use Illuminate\Console\Command;

class SyncLinkTrackingConversionsCommand extends Command
{
    protected $signature = 'link-tracking:sync-conversions
                            {--chunk=200 : Orders to process per chunk}
                            {--dry-run : Report matches without updating visits}';

    protected $description = 'Backfill converted visit links for paid partner orders missing a matching conversion';

    public function handle(LinkTrackingService $linkTracking): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $scanned = 0;
        $linked = 0;
        $skipped = 0;

        DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->where(function ($query): void {
                $query->whereNotNull('partner_user_id')
                    ->orWhere(function ($legacy): void {
                        $legacy->whereNotNull('partner_code')
                            ->where('partner_code', '!=', '');
                    });
            })
            ->orderBy('id')
            ->chunkById($chunk, function ($orders) use ($linkTracking, $dryRun, &$scanned, &$linked, &$skipped): void {
                foreach ($orders as $order) {
                    $scanned++;

                    $alreadyConverted = LinkTrackingVisit::query()
                        ->where('donation_order_id', $order->id)
                        ->where('converted', true)
                        ->exists();

                    if ($alreadyConverted) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $candidate = $linkTracking->attachLatestUnconvertedVisit($order, null, false);

                        if ($candidate !== null) {
                            $linked++;
                            $this->line("Would link visit #{$candidate->id} → order #{$order->id}");
                        } else {
                            $skipped++;
                        }

                        continue;
                    }

                    $visit = $linkTracking->markConverted($order->fresh());

                    if ($visit !== null && $visit->converted && (int) $visit->donation_order_id === (int) $order->id) {
                        $linked++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $mode = $dryRun ? 'Dry-run: ' : '';
        $this->info("{$mode}Scanned {$scanned} paid partner order(s); linked {$linked}; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
