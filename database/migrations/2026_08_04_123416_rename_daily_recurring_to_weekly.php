<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('causes', 'allow_daily_recurring') && ! Schema::hasColumn('causes', 'allow_weekly_recurring')) {
            Schema::table('causes', function (Blueprint $table) {
                $table->renameColumn('allow_daily_recurring', 'allow_weekly_recurring');
            });
        }

        if (Schema::hasColumn('donation_campaigns', 'frequency')) {
            DB::table('donation_campaigns')
                ->where('frequency', 'daily')
                ->update(['frequency' => 'weekly']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('donation_campaigns', 'frequency')) {
            DB::table('donation_campaigns')
                ->where('frequency', 'weekly')
                ->update(['frequency' => 'daily']);
        }

        if (Schema::hasColumn('causes', 'allow_weekly_recurring') && ! Schema::hasColumn('causes', 'allow_daily_recurring')) {
            Schema::table('causes', function (Blueprint $table) {
                $table->renameColumn('allow_weekly_recurring', 'allow_daily_recurring');
            });
        }
    }
};
