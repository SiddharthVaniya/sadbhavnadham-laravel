<?php

namespace App\Console\Commands;

use App\Jobs\SyncMetaAdSpendJob;
use Illuminate\Console\Command;

class SyncMetaAdSpendCommand extends Command
{
    protected $signature = 'meta:sync-ad-spend
                            {--account= : Optional meta_ad_accounts.id}
                            {--from= : Inclusive start date Y-m-d}
                            {--to= : Inclusive end date Y-m-d}
                            {--sync : Run synchronously instead of queueing}';

    protected $description = 'Pull Meta Ads Insights spend into marketer daily budgets';

    public function handle(): int
    {
        $accountId = $this->option('account');
        $job = new SyncMetaAdSpendJob(
            $accountId !== null && $accountId !== '' ? (int) $accountId : null,
            $this->option('from') ?: null,
            $this->option('to') ?: null,
        );

        if ($this->option('sync')) {
            $result = $job->handle(app(\App\Services\Meta\MetaAdSpendSyncService::class));
            $this->info(sprintf(
                'Synced %d account(s), %d failed, %d insight rows, %d marketer day updates.',
                $result['accounts_synced'],
                $result['accounts_failed'],
                $result['rows_upserted'],
                $result['marketers_updated'],
            ));

            foreach ($result['errors'] as $error) {
                $this->warn($error);
            }

            return $result['accounts_failed'] > 0 && $result['accounts_synced'] === 0
                ? self::FAILURE
                : self::SUCCESS;
        }

        SyncMetaAdSpendJob::dispatch(
            $accountId !== null && $accountId !== '' ? (int) $accountId : null,
            $this->option('from') ?: null,
            $this->option('to') ?: null,
        );

        $this->info('Meta ad spend sync job queued.');

        return self::SUCCESS;
    }
}
