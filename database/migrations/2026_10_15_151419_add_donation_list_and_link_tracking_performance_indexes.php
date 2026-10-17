<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->index('utm_campaign', 'donation_orders_utm_campaign_index');
            $table->index('receipt_number', 'donation_orders_receipt_number_index');
        });

        Schema::table('link_tracking_visits', function (Blueprint $table) {
            $table->index(['sid', 'created_at'], 'link_tracking_visits_sid_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropIndex('donation_orders_utm_campaign_index');
            $table->dropIndex('donation_orders_receipt_number_index');
        });

        Schema::table('link_tracking_visits', function (Blueprint $table) {
            $table->dropIndex('link_tracking_visits_sid_created_at_index');
        });
    }
};
