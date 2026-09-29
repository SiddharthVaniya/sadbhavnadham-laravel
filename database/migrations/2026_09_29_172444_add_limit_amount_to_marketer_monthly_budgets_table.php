<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_monthly_budgets', function (Blueprint $table) {
            $table->unsignedInteger('limit_amount')->nullable()->after('target_amount');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_monthly_budgets', function (Blueprint $table) {
            $table->dropColumn('limit_amount');
        });
    }
};
