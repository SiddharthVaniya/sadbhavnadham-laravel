<?php

namespace App\Console\Commands;

use App\Models\LinkTrackingVisit;
use App\Services\GeoIp\GeoIpLookupService;
use Illuminate\Console\Command;

class GeoIpBackfillVisitsCommand extends Command
{
    protected $signature = 'geoip:backfill-visits
                            {--chunk=200 : Rows per chunk}
                            {--limit=0 : Max rows to update (0 = all)}
                            {--dry-run : Show how many rows would be updated}';

    protected $description = 'Backfill geo columns on link_tracking_visits from stored IP addresses';

    public function handle(GeoIpLookupService $geoIp): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $base = LinkTrackingVisit::query()
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->whereNull('ip_country_code')
            ->orderBy('id');

        $total = (clone $base)->count();
        $target = $limit > 0 ? min($limit, $total) : $total;

        if ($target === 0) {
            $this->info('No visits need geo backfill.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Would update' : 'Updating')." {$target} visit(s)…");

        if ($dryRun) {
            return self::SUCCESS;
        }

        $updated = 0;

        $base->chunkById($chunk, function ($visits) use ($geoIp, $limit, &$updated): bool {
            foreach ($visits as $visit) {
                if ($limit > 0 && $updated >= $limit) {
                    return false;
                }

                $result = $geoIp->lookup($visit->ip_address);
                $columns = $result->toVisitColumns();
                unset($columns['ip_address']);

                $payload = array_filter(
                    $columns,
                    static fn ($value) => $value !== null && $value !== '',
                );

                if ($payload === []) {
                    continue;
                }

                $visit->forceFill($payload)->save();
                $updated++;
            }

            return true;
        });

        $this->info("Backfilled geo on {$updated} visit(s).");

        return self::SUCCESS;
    }
}
