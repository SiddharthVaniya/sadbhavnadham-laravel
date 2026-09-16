<?php

namespace App\Console\Commands;

use App\Services\LegacyDonationImportService;
use Illuminate\Console\Command;

class ImportLegacyDonationsCommand extends Command
{
    protected $signature = 'donations:import-legacy
                            {path : Absolute path to the legacy .sql dump}
                            {--dry-run : Parse and report only (default if --execute is missing)}
                            {--execute : Actually write donors / orders / items}
                            {--limit= : Import at most N rows (useful for a small test)}';

    protected $description = 'Import donations from the old website SQL dump into donation_orders';

    public function handle(LegacyDonationImportService $importer): int
    {
        $path = (string) $this->argument('path');
        $execute = (bool) $this->option('execute');
        $dryRun = ! $execute || (bool) $this->option('dry-run');

        if ($execute && $this->option('dry-run')) {
            $this->error('Use either --dry-run or --execute, not both.');

            return self::FAILURE;
        }

        // Safety: without --execute always dry-run.
        if (! $execute) {
            $dryRun = true;
        }

        $limit = $this->option('limit');
        $limit = $limit !== null && $limit !== '' ? (int) $limit : null;

        $this->info($dryRun ? 'DRY RUN — no database writes.' : 'EXECUTE — writing to the database.');
        $this->line('File: '.$path);

        $stats = $importer->importFromSqlFile($path, $dryRun, $limit);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Rows parsed', $stats['total']],
                ['Legacy rows with address', $stats['with_address']],
                [$dryRun ? 'Would import' : 'Imported', $dryRun ? $stats['would_import'] : $stats['imported']],
                ['Backfilled address/PAN', $stats['backfilled']],
                ['Skipped (already exist)', $stats['skipped_existing']],
                ['Errors', count($stats['errors'])],
            ]
        );

        if ($stats['by_status'] !== []) {
            $this->newLine();
            $this->info('By mapped status:');
            foreach ($stats['by_status'] as $status => $count) {
                $this->line("  {$status}: {$count}");
            }
        }

        if ($stats['by_cause'] !== []) {
            $this->newLine();
            $this->info('By legacy donate_for:');
            arsort($stats['by_cause']);
            foreach ($stats['by_cause'] as $cause => $count) {
                $mapped = $importer->mapCauseSlug($cause === '(empty)' ? '' : $cause) ?? '—';
                $this->line("  {$cause} → {$mapped}: {$count}");
            }
        }

        if ($stats['unmapped_causes'] !== []) {
            $this->newLine();
            $this->warn('Unmapped causes (imported without cause_id):');
            foreach ($stats['unmapped_causes'] as $cause => $count) {
                $this->line("  {$cause}: {$count}");
            }
        }

        if ($stats['errors'] !== []) {
            $this->newLine();
            $this->error('Errors:');
            foreach (array_slice($stats['errors'], 0, 20) as $error) {
                $this->line('  - '.$error);
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Looks good? Run again with --execute to import.');
        }

        return count($stats['errors']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
