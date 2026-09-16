<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'send_donation_certificate'],
            [
                'value' => '1',
                'label' => 'Send Donation Certificate on WhatsApp',
                'description' => 'When enabled, a personalized sanman patra certificate image is attached to the thank you WhatsApp message after a successful donation.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'send_donation_certificate')->delete();
    }
};
