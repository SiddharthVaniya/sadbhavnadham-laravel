<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'attach_receipt_pdf'],
            [
                'value' => '0',
                'label' => 'Attach PDF to receipt email',
                'description' => 'When enabled, the donation receipt PDF is attached to the receipt email. Keep off if PDF generation is slow or unreliable on this server.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'attach_receipt_pdf')->delete();
    }
};
