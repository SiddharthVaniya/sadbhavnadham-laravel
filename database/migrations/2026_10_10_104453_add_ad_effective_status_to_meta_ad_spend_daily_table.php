<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ad_spend_daily', function (Blueprint $table) {
            $table->string('ad_effective_status', 32)->nullable()->after('ad_name');
            $table->index('ad_effective_status');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ad_spend_daily', function (Blueprint $table) {
            $table->dropIndex(['ad_effective_status']);
            $table->dropColumn('ad_effective_status');
        });
    }
};
