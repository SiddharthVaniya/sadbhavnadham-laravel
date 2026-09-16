<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->string('utm_term', 120)->nullable()->after('utm_content');
            $table->string('attr_source', 32)->nullable()->after('utm_term');
            $table->string('attr_medium', 32)->nullable()->after('attr_source');
            $table->string('attr_platform', 32)->nullable()->after('attr_medium');
            $table->string('attr_placement', 64)->nullable()->after('attr_platform');

            $table->index('attr_source');
            $table->index('attr_platform');
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('utm_term', 120)->nullable()->after('utm_content');
            $table->string('attr_source', 32)->nullable()->after('utm_term');
            $table->string('attr_medium', 32)->nullable()->after('attr_source');
            $table->string('attr_platform', 32)->nullable()->after('attr_medium');
            $table->string('attr_placement', 64)->nullable()->after('attr_platform');

            $table->index('attr_source');
            $table->index('attr_platform');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropIndex(['attr_source']);
            $table->dropIndex(['attr_platform']);
            $table->dropColumn([
                'utm_term',
                'attr_source',
                'attr_medium',
                'attr_platform',
                'attr_placement',
            ]);
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['attr_source']);
            $table->dropIndex(['attr_platform']);
            $table->dropColumn([
                'utm_term',
                'attr_source',
                'attr_medium',
                'attr_platform',
                'attr_placement',
            ]);
        });
    }
};
