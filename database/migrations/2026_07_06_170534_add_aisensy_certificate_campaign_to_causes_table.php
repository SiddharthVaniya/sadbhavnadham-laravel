<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->string('aisensy_certificate_campaign')->nullable()->after('aisensy_thank_you_campaign');
        });

        DB::table('settings')
            ->where('key', 'send_donation_certificate')
            ->update([
                'description' => 'When enabled, a separate WhatsApp message with the personalized sanman patra certificate image is sent after a successful donation.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn('aisensy_certificate_campaign');
        });

        DB::table('settings')
            ->where('key', 'send_donation_certificate')
            ->update([
                'description' => 'When enabled, a personalized sanman patra certificate image is attached to the thank you WhatsApp message after a successful donation.',
                'updated_at' => now(),
            ]);
    }
};
