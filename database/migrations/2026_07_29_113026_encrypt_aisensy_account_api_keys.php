<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accounts = DB::table('aisensy_accounts')->select('id', 'api_key')->get();

        foreach ($accounts as $account) {
            $apiKey = (string) ($account->api_key ?? '');

            if ($apiKey === '') {
                continue;
            }

            try {
                Crypt::decryptString($apiKey);

                // Already encrypted under the current APP_KEY.
                continue;
            } catch (\Throwable) {
                DB::table('aisensy_accounts')
                    ->where('id', $account->id)
                    ->update(['api_key' => Crypt::encryptString($apiKey)]);
            }
        }
    }

    public function down(): void
    {
        $accounts = DB::table('aisensy_accounts')->select('id', 'api_key')->get();

        foreach ($accounts as $account) {
            $apiKey = (string) ($account->api_key ?? '');

            if ($apiKey === '') {
                continue;
            }

            try {
                $plain = Crypt::decryptString($apiKey);

                DB::table('aisensy_accounts')
                    ->where('id', $account->id)
                    ->update(['api_key' => $plain]);
            } catch (\Throwable) {
                // Leave as-is if value is not decryptable.
            }
        }
    }
};
