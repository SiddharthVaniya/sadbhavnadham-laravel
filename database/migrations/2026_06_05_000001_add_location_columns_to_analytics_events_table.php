<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('referrer');
            $table->string('country_code', 2)->nullable()->after('ip_address');
            $table->string('country_name', 100)->nullable()->after('country_code');
            $table->string('region_name', 100)->nullable()->after('country_name');
            $table->string('city', 100)->nullable()->after('region_name');

            $table->index(['country_code', 'created_at']);
            $table->index(['region_name', 'created_at']);
            $table->index(['city', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['country_code', 'created_at']);
            $table->dropIndex(['region_name', 'created_at']);
            $table->dropIndex(['city', 'created_at']);

            $table->dropColumn([
                'ip_address',
                'country_code',
                'country_name',
                'region_name',
                'city',
            ]);
        });
    }
};
