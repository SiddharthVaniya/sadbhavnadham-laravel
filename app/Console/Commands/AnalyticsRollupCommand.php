<?php

namespace App\Console\Commands;

use App\Services\AnalyticsRollupService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AnalyticsRollupCommand extends Command
{
    protected $signature = 'analytics:rollup
                            {--day= : Roll up a single date (Y-m-d)}
                            {--yesterday : Roll up yesterday}
                            {--from= : Start date for backfill (Y-m-d)}
                            {--to= : End date for backfill (Y-m-d, defaults to today)}';

    protected $description = 'Aggregate analytics_events into daily rollup tables';

    public function handle(AnalyticsRollupService $rollupService): int
    {
        if (! config('analytics.rollup_enabled', true)) {
            $this->warn('Analytics rollups are disabled (analytics.rollup_enabled=false).');

            return self::SUCCESS;
        }

        if ($this->option('yesterday')) {
            $day = now()->subDay()->startOfDay();
            $rollupService->rollupDay($day);
            $this->info('Rolled up '.$day->toDateString());

            return self::SUCCESS;
        }

        if ($this->option('day')) {
            $day = Carbon::parse((string) $this->option('day'), config('app.timezone'))->startOfDay();
            $rollupService->rollupDay($day);
            $this->info('Rolled up '.$day->toDateString());

            return self::SUCCESS;
        }

        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        if (! $fromOption && ! $toOption) {
            $day = now()->startOfDay();
            $rollupService->rollupDay($day);
            $this->info('Rolled up '.$day->toDateString());

            return self::SUCCESS;
        }

        $from = $fromOption
            ? Carbon::parse((string) $fromOption, config('app.timezone'))->startOfDay()
            : ($rollupService->earliestEventDate() ?? now()->startOfDay());

        $to = $toOption
            ? Carbon::parse((string) $toOption, config('app.timezone'))->startOfDay()
            : now()->startOfDay();

        if ($from->gt($to)) {
            $this->error('The --from date must be on or before --to.');

            return self::FAILURE;
        }

        $this->info('Rolling up '.$from->toDateString().' → '.$to->toDateString());

        $result = $rollupService->rollupRange($from, $to);

        $this->info("Done. Processed {$result['days']} day(s).");

        return self::SUCCESS;
    }
}
