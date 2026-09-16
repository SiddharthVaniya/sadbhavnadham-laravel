<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaign_runs', function (Blueprint $table) {
            $table->unsignedInteger('delivery_sent_count')->nullable()->after('skipped_count');
            $table->unsignedInteger('delivery_delivered_count')->nullable()->after('delivery_sent_count');
            $table->unsignedInteger('delivery_read_count')->nullable()->after('delivery_delivered_count');
            $table->unsignedInteger('delivery_failed_count')->nullable()->after('delivery_read_count');
            $table->timestamp('delivery_synced_at')->nullable()->after('delivery_failed_count');
            $table->json('delivery_stats_json')->nullable()->after('delivery_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaign_runs', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_sent_count',
                'delivery_delivered_count',
                'delivery_read_count',
                'delivery_failed_count',
                'delivery_synced_at',
                'delivery_stats_json',
            ]);
        });
    }
};
