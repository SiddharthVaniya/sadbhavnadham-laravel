<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->string('source_channel', 32)->nullable()->after('payment_provider');
            $table->string('utm_source', 120)->nullable()->after('source_channel');
            $table->string('utm_medium', 120)->nullable()->after('utm_source');
            $table->string('utm_campaign', 120)->nullable()->after('utm_medium');
            $table->string('referrer', 512)->nullable()->after('utm_campaign');
            $table->string('landing_path', 255)->nullable()->after('referrer');
            $table->string('device_type', 32)->nullable()->after('landing_path');
            $table->foreignId('source_campaign_id')
                ->nullable()
                ->after('device_type')
                ->constrained('donation_campaigns')
                ->nullOnDelete();

            $table->index('source_channel');
            $table->index('utm_source');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_campaign_id');
            $table->dropIndex(['source_channel']);
            $table->dropIndex(['utm_source']);
            $table->dropColumn([
                'source_channel',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'referrer',
                'landing_path',
                'device_type',
            ]);
        });
    }
};
