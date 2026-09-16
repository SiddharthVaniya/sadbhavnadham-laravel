<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('causes')
            ->where('aisensy_receipt_campaign', 'donation_receipt_confirmed')
            ->update([
                'aisensy_receipt_campaign' => 'donation_receipt_pdf',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('causes')
            ->where('aisensy_receipt_campaign', 'donation_receipt_pdf')
            ->update([
                'aisensy_receipt_campaign' => 'donation_receipt_confirmed',
                'updated_at' => now(),
            ]);
    }
};
