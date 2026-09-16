<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultCampaign = trim((string) config('services.aisensy.receipt_campaign', 'donation_receipt_pdf'));

        if ($defaultCampaign === '') {
            return;
        }

        DB::table('causes')
            ->whereNotNull('aisensy_account_id')
            ->where(function ($query): void {
                $query->whereNull('aisensy_receipt_campaign')
                    ->orWhere('aisensy_receipt_campaign', '');
            })
            ->update([
                'aisensy_receipt_campaign' => $defaultCampaign,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Non-destructive: keep configured campaign names on causes.
    }
};
