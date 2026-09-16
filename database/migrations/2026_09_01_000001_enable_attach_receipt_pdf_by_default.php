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
                'value' => '1',
                'label' => 'Attach PDF to receipt email',
                'description' => 'When enabled, the donation-minimal receipt PDF is attached to the receipt email sent after payment confirmation.',
                'group' => 'notifications',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'attach_receipt_pdf')
            ->update([
                'value' => '0',
                'updated_at' => now(),
            ]);
    }
};
