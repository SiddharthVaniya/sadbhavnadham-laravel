<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('device_type', 20)->nullable()->after('referrer');
            $table->string('utm_source', 120)->nullable()->after('device_type');
            $table->string('utm_medium', 120)->nullable()->after('utm_source');
            $table->string('utm_campaign', 120)->nullable()->after('utm_medium');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropColumn(['device_type', 'utm_source', 'utm_medium', 'utm_campaign']);
        });
    }
};
