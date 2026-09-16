<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->date('birthday_whatsapp_sent_on')->nullable()->after('last_donated_at');
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'send_birthday_whatsapp'],
            [
                'value' => '0',
                'label' => 'Send Birthday WhatsApp',
                'description' => 'When enabled, donors with a birth date and valid mobile receive a personalized Happy Birthday image on WhatsApp each year on their birthday.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'aisensy_birthday_campaign'],
            [
                'value' => '',
                'label' => 'AiSensy Birthday Campaign',
                'description' => 'Exact AiSensy campaign name used for birthday media messages. Leave blank until the campaign is created in AiSensy.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'aisensy_birthday_account_id'],
            [
                'value' => '',
                'label' => 'AiSensy Birthday Account ID',
                'description' => 'Optional aisensy_accounts.id for birthday sends. Leave blank to use the first active AiSensy account.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->dropColumn('birthday_whatsapp_sent_on');
        });

        DB::table('settings')->whereIn('key', [
            'send_birthday_whatsapp',
            'aisensy_birthday_campaign',
            'aisensy_birthday_account_id',
        ])->delete();
    }
};
