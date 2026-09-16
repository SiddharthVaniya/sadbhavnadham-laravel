<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'log_failed_donations_to_sheet'],
            [
                'value' => '0',
                'label' => 'Log failed donations to Google Sheet',
                'description' => 'When enabled, failed/abandoned checkouts are appended to Google Sheets. Keep off to log only captured (paid) donations.',
                'group' => 'integrations',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'log_failed_donations_to_sheet')->delete();
    }
};
