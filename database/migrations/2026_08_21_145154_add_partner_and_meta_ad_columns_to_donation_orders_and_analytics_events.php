<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->foreignId('partner_user_id')->nullable()->after('attr_placement')
                ->constrained('users')->nullOnDelete();
            $table->string('partner_code', 40)->nullable()->after('partner_user_id');
            $table->string('meta_campaign_id', 40)->nullable()->after('partner_code');
            $table->string('meta_adset_id', 40)->nullable()->after('meta_campaign_id');
            $table->string('meta_ad_id', 40)->nullable()->after('meta_adset_id');

            $table->index('meta_campaign_id');
            $table->index('meta_ad_id');
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->foreignId('partner_user_id')->nullable()->after('attr_placement')
                ->constrained('users')->nullOnDelete();
            $table->string('partner_code', 40)->nullable()->after('partner_user_id');
            $table->string('meta_campaign_id', 40)->nullable()->after('partner_code');
            $table->string('meta_adset_id', 40)->nullable()->after('meta_campaign_id');
            $table->string('meta_ad_id', 40)->nullable()->after('meta_adset_id');

            $table->index('meta_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropForeign(['partner_user_id']);
            $table->dropIndex(['meta_campaign_id']);
            $table->dropIndex(['meta_ad_id']);
            $table->dropColumn([
                'partner_user_id',
                'partner_code',
                'meta_campaign_id',
                'meta_adset_id',
                'meta_ad_id',
            ]);
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropForeign(['partner_user_id']);
            $table->dropIndex(['meta_campaign_id']);
            $table->dropColumn([
                'partner_user_id',
                'partner_code',
                'meta_campaign_id',
                'meta_adset_id',
                'meta_ad_id',
            ]);
        });
    }
};
