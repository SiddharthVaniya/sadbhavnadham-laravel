<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'send_receipt_whatsapp'],
            [
                'value' => '1',
                'label' => 'Send Donation Receipt on WhatsApp',
                'description' => 'When enabled, a separate WhatsApp message with the donation receipt PDF is sent after a successful donation.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'send_receipt_whatsapp')->delete();
    }
};
