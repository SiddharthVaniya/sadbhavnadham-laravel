<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\DonationOrder;
use App\Services\DonationAttributionService;
use App\Support\Attribution\AttributionNormalizer;
use App\Support\Attribution\AttributionParameters;
use Illuminate\Console\Command;

class BackfillDonationAttributionCommand extends Command
{
    protected $signature = 'donations:backfill-attribution
                            {--chunk=200 : Orders to process per chunk}
                            {--events : Also normalize analytics event attribution columns}';

    protected $description = 'Backfill donation order attribution from checkout analytics events and payment provider';

    public function handle(DonationAttributionService $donationAttribution): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $updated = 0;
        $scanned = 0;

        DonationOrder::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($orders) use ($donationAttribution, &$updated, &$scanned): void {
                foreach ($orders as $order) {
                    $scanned++;

                    if ($donationAttribution->backfillOrder($order)) {
                        $updated++;
                    }
                }
            });

        $this->info("Scanned {$scanned} order(s); updated {$updated}.");

        if ($this->option('events')) {
            $eventsUpdated = 0;
            $eventsScanned = 0;

            AnalyticsEvent::query()
                ->orderBy('id')
                ->chunkById($chunk, function ($events) use (&$eventsUpdated, &$eventsScanned): void {
                    foreach ($events as $event) {
                        $eventsScanned++;

                        $normalized = [
                            ...AttributionNormalizer::normalizedPayload([
                                'utm_source' => $event->utm_source,
                                'utm_medium' => $event->utm_medium,
                                'utm_campaign' => $event->utm_campaign,
                                'utm_content' => $event->utm_content,
                                'utm_term' => $event->utm_term,
                                'referrer' => $event->referrer,
                                'landing_path' => $event->path ?? null,
                            ]),
                            ...AttributionParameters::columnsFromPayload([
                                'sid' => $event->partner_code,
                                'utm_id' => $event->meta_campaign_id,
                                'utm_term' => $event->meta_adset_id,
                                'aid' => $event->meta_ad_id,
                                'utm_source' => $event->utm_source,
                                'utm_content' => $event->utm_content,
                            ]),
                        ];

                        if ($normalized === []) {
                            continue;
                        }

                        $before = $event->only(array_keys($normalized));
                        $event->forceFill($normalized)->save();

                        if ($before !== $normalized) {
                            $eventsUpdated++;
                        }
                    }
                });

            $this->info("Scanned {$eventsScanned} analytics event(s); updated {$eventsUpdated}.");
        }

        return self::SUCCESS;
    }
}
