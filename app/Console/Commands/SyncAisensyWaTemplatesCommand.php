<?php

namespace App\Console\Commands;

use App\Models\AisensyAccount;
use App\Services\AiSensy\AiSensyProjectClient;
use Illuminate\Console\Command;

class SyncAisensyWaTemplatesCommand extends Command
{
    protected $signature = 'aisensy:sync-templates {--account= : AiSensy account id}';

    protected $description = 'Sync approved WhatsApp templates from AiSensy Project API';

    public function handle(AiSensyProjectClient $client): int
    {
        $query = AisensyAccount::query()->where('is_active', true);

        if ($this->option('account')) {
            $query->whereKey((int) $this->option('account'));
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active AiSensy accounts found.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            if (! $account->hasProjectApiPassword()) {
                $this->line("Skipping {$account->name}: no Project API password.");

                continue;
            }

            $count = $client->syncApprovedTemplates($account);
            $this->info("{$account->name}: synced {$count} approved template(s).");
        }

        return self::SUCCESS;
    }
}
