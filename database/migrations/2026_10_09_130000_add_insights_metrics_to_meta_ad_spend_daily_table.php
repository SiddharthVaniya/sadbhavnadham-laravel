<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ad_spend_daily', function (Blueprint $table) {
            $table->unsignedBigInteger('impressions')->default(0)->after('spend_amount');
            $table->unsignedInteger('clicks')->default(0)->after('impressions');
            $table->unsignedBigInteger('reach')->default(0)->after('clicks');
            $table->unsignedInteger('inline_link_clicks')->default(0)->after('reach');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ad_spend_daily', function (Blueprint $table) {
            $table->dropColumn(['impressions', 'clicks', 'reach', 'inline_link_clicks']);
        });
    }
};
