<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_items', function (Blueprint $table) {
            $table->foreignId('donation_campaign_id')
                ->nullable()
                ->after('cause_package_id')
                ->constrained('donation_campaigns')
                ->nullOnDelete();
        });

        Schema::table('donation_subscriptions', function (Blueprint $table) {
            $table->foreignId('donation_campaign_id')
                ->nullable()
                ->after('cause_package_id')
                ->constrained('donation_campaigns')
                ->nullOnDelete();
        });

        $this->backfillCampaignIds('donation_items');
        $this->backfillCampaignIds('donation_subscriptions');
    }

    public function down(): void
    {
        Schema::table('donation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('donation_campaign_id');
        });

        Schema::table('donation_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('donation_campaign_id');
        });
    }

    private function backfillCampaignIds(string $table): void
    {
        DB::table($table)
            ->whereNull('donation_campaign_id')
            ->whereNotNull('meta')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->meta, true);

                    if (! is_array($meta)) {
                        continue;
                    }

                    $campaignId = $meta['campaign']['id'] ?? null;

                    if (! is_numeric($campaignId)) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['donation_campaign_id' => (int) $campaignId]);
                }
            });
    }
};
