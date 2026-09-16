<?php

namespace App\Console\Commands;

use App\Jobs\LogDonationToSheetJob;
use App\Models\DonationOrder;
use App\Services\GoogleSheetsLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillDonationSheets extends Command
{
    protected $signature = 'donations:backfill-sheets
                            {--sync : Log to Google Sheets immediately instead of queueing}
                            {--dry-run : Show which orders would be backfilled}';

    protected $description = 'Backfill paid donations that are missing from Google Sheets';

    public function handle(GoogleSheetsLogger $sheetsLogger): int
    {
        $orders = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->whereNull('sheet_logged_at')
            ->with('items')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No paid donations are missing from Google Sheets.');

            return self::SUCCESS;
        }

        $this->info("Found {$orders->count()} paid donation(s) not logged to Google Sheets.");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Receipt', 'Donor', 'Amount', 'Paid at'],
                $orders->map(fn (DonationOrder $order) => [
                    $order->id,
                    $order->hasReceipt() ? $order->receiptNumberFormatted() : '—',
                    $order->donor_name,
                    $order->total_amount,
                    $order->paid_at?->format('Y-m-d H:i:s') ?? '—',
                ])->all()
            );

            return self::SUCCESS;
        }

        $failedJobCount = DB::table('failed_jobs')
            ->where('payload', 'like', '%LogDonationToSheetJob%')
            ->count();

        if ($failedJobCount > 0) {
            $this->warn("Clearing {$failedJobCount} failed Google Sheet job(s) before backfill.");
            DB::table('failed_jobs')
                ->where('payload', 'like', '%LogDonationToSheetJob%')
                ->delete();
        }

        $logged = 0;
        $errors = 0;

        foreach ($orders as $order) {
            $label = $order->hasReceipt()
                ? $order->receiptNumberFormatted()
                : "#{$order->id}";

            try {
                if ($this->option('sync')) {
                    (new LogDonationToSheetJob($order))->handle($sheetsLogger);
                } else {
                    LogDonationToSheetJob::dispatch($order);
                }

                $logged++;
                $this->line("Queued sheet log for {$label} ({$order->donor_name})");
            } catch (\Throwable $e) {
                $errors++;
                $this->error("Failed {$label}: {$e->getMessage()}");
            }
        }

        if ($this->option('sync')) {
            $this->info("Backfilled {$logged} donation(s) to Google Sheets.");
        } else {
            $this->info("Queued {$logged} donation(s) for Google Sheets logging.");
        }

        if ($errors > 0) {
            $this->warn("{$errors} donation(s) could not be backfilled. Check storage/logs/laravel.log for details.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
