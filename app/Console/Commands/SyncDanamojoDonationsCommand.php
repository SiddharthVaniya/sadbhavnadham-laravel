<?php

namespace App\Console\Commands;

use App\Services\Danamojo\DanamojoDonationImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncDanamojoDonationsCommand extends Command
{
    protected $signature = 'danamojo:sync
                            {--from= : Start date (Y-m-d), defaults to lookback window}
                            {--to= : End date (Y-m-d), defaults to today}';

    protected $description = 'Import verified Danamojo donations into the portal';

    public function handle(DanamojoDonationImporter $importer): int
    {
        if (! config('danamojo.enabled', true)) {
            $this->warn('Danamojo sync is disabled (DANAMOJO_SYNC_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! filled(config('danamojo.api_key_secret'))) {
            $this->error('Set DANAMOJO_API_KEY_SECRET before running danamojo:sync.');

            return self::FAILURE;
        }

        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'), config('app.timezone'))->startOfDay()
            : now()->startOfDay();

        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'), config('app.timezone'))->startOfDay()
            : $to->copy()->subDays(max(0, (int) config('danamojo.lookback_days', 3)));

        if ($from->gt($to)) {
            $this->error('The --from date must be on or before --to.');

            return self::FAILURE;
        }

        $this->info('Syncing Danamojo donations '.$from->toDateString().' → '.$to->toDateString());

        try {
            $stats = $importer->sync($from, $to);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Fetched', 'Imported', 'Updated', 'Skipped'],
            [[$stats['fetched'], $stats['imported'], $stats['updated'], $stats['skipped']]],
        );

        return self::SUCCESS;
    }
}
