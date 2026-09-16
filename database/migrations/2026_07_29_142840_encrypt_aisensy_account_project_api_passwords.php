<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accounts = DB::table('aisensy_accounts')->select('id', 'project_api_password')->get();

        foreach ($accounts as $account) {
            $password = (string) ($account->project_api_password ?? '');

            if ($password === '') {
                continue;
            }

            try {
                Crypt::decryptString($password);

                // Already encrypted under the current APP_KEY.
                continue;
            } catch (\Throwable) {
                DB::table('aisensy_accounts')
                    ->where('id', $account->id)
                    ->update(['project_api_password' => Crypt::encryptString($password)]);
            }
        }
    }

    public function down(): void
    {
        $accounts = DB::table('aisensy_accounts')->select('id', 'project_api_password')->get();

        foreach ($accounts as $account) {
            $password = (string) ($account->project_api_password ?? '');

            if ($password === '') {
                continue;
            }

            try {
                $plain = Crypt::decryptString($password);

                DB::table('aisensy_accounts')
                    ->where('id', $account->id)
                    ->update(['project_api_password' => $plain]);
            } catch (\Throwable) {
                // Leave as-is when the value is not decryptable.
            }
        }
    }
};
