<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->string('utm_content', 120)->nullable()->after('utm_campaign');
            $table->index('utm_content');
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('utm_content', 120)->nullable()->after('utm_campaign');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropIndex(['utm_content']);
            $table->dropColumn('utm_content');
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropColumn('utm_content');
        });
    }
};
