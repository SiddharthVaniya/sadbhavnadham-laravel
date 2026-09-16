<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recurring donations previously carried no attribution at all: the subscription
        // was created at checkout but every recurring order was built later by the
        // webhook, which has no access to the original request. Persist attribution on
        // the subscription so each cycle can inherit it.
        Schema::table('donation_subscriptions', function (Blueprint $table) {
            $table->string('source_channel', 32)->nullable()->after('meta');
            $table->string('utm_source', 120)->nullable()->after('source_channel');
            $table->string('utm_medium', 120)->nullable()->after('utm_source');
            $table->string('utm_campaign', 120)->nullable()->after('utm_medium');
            $table->string('utm_content', 120)->nullable()->after('utm_campaign');
            $table->string('utm_term', 120)->nullable()->after('utm_content');
            $table->string('attr_source', 32)->nullable()->after('utm_term');
            $table->string('attr_medium', 32)->nullable()->after('attr_source');
            $table->string('attr_platform', 32)->nullable()->after('attr_medium');
            $table->string('attr_placement', 64)->nullable()->after('attr_platform');
            $table->foreignId('partner_user_id')->nullable()->after('attr_placement')
                ->constrained('users')->nullOnDelete();
            $table->string('partner_code', 40)->nullable()->after('partner_user_id');
            $table->string('meta_campaign_id', 40)->nullable()->after('partner_code');
            $table->string('meta_adset_id', 40)->nullable()->after('meta_campaign_id');
            $table->string('meta_ad_id', 40)->nullable()->after('meta_adset_id');
            $table->string('referrer', 512)->nullable()->after('meta_ad_id');
            $table->string('landing_path', 255)->nullable()->after('referrer');
            $table->string('device_type', 32)->nullable()->after('landing_path');
        });

        // Attribution reports always filter status + paid_at and then group by
        // partner/source; single-column indexes forced the optimiser to pick one.
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->index(['status', 'paid_at', 'partner_user_id'], 'donation_orders_status_paid_partner_index');
            $table->index(['status', 'paid_at', 'attr_source'], 'donation_orders_status_paid_attr_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropIndex('donation_orders_status_paid_partner_index');
            $table->dropIndex('donation_orders_status_paid_attr_source_index');
        });

        Schema::table('donation_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['partner_user_id']);
            $table->dropColumn([
                'source_channel',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_content',
                'utm_term',
                'attr_source',
                'attr_medium',
                'attr_platform',
                'attr_placement',
                'partner_user_id',
                'partner_code',
                'meta_campaign_id',
                'meta_adset_id',
                'meta_ad_id',
                'referrer',
                'landing_path',
                'device_type',
            ]);
        });
    }
};
