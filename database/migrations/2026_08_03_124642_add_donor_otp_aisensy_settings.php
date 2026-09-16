<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'aisensy_otp_campaign'],
            [
                'value' => '',
                'label' => 'AiSensy OTP Campaign',
                'description' => 'Exact AiSensy campaign name for returning-donor OTP WhatsApp messages. Template must have exactly 1 body variable: {{1}} = OTP code. Leave blank to use email OTP only.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'aisensy_otp_account_id'],
            [
                'value' => '',
                'label' => 'AiSensy OTP Account ID',
                'description' => 'Optional aisensy_accounts.id for OTP WhatsApp sends. Leave blank to use the first active AiSensy account.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'aisensy_otp_campaign',
            'aisensy_otp_account_id',
        ])->delete();
    }
};
