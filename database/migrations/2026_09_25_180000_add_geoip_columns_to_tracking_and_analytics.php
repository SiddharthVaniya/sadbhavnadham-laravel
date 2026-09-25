<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_tracking_visits', function (Blueprint $table) {
            $table->string('ip_country_code', 2)->nullable()->after('ip_address');
            $table->string('ip_country_name', 100)->nullable()->after('ip_country_code');
            $table->string('ip_region_name', 100)->nullable()->after('ip_country_name');
            $table->string('ip_city', 100)->nullable()->after('ip_region_name');
            $table->string('ip_postal_code', 32)->nullable()->after('ip_city');
            $table->decimal('ip_lat', 10, 7)->nullable()->after('ip_postal_code');
            $table->decimal('ip_lng', 10, 7)->nullable()->after('ip_lat');
            $table->string('ip_timezone', 64)->nullable()->after('ip_lng');
            $table->unsignedInteger('ip_asn')->nullable()->after('ip_timezone');
            $table->string('ip_isp', 255)->nullable()->after('ip_asn');

            $table->index('ip_country_code');
            $table->index('ip_city');
            $table->index('ip_isp');
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('postal_code', 32)->nullable()->after('city');
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('timezone', 64)->nullable()->after('longitude');
            $table->unsignedInteger('asn')->nullable()->after('timezone');
            $table->string('isp', 255)->nullable()->after('asn');

            $table->index('isp');
        });

        Schema::table('donation_orders', function (Blueprint $table) {
            $table->string('ip_postal_code', 32)->nullable()->after('ip_city');
            $table->string('ip_timezone', 64)->nullable()->after('ip_lng');
            $table->unsignedInteger('ip_asn')->nullable()->after('ip_timezone');
            $table->string('ip_isp', 255)->nullable()->after('ip_asn');
        });

        Schema::table('user_login_logs', function (Blueprint $table) {
            $table->string('postal_code', 32)->nullable()->after('location');
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('timezone', 64)->nullable()->after('longitude');
            $table->unsignedInteger('asn')->nullable()->after('timezone');
            $table->string('isp', 255)->nullable()->after('asn');
        });
    }

    public function down(): void
    {
        Schema::table('link_tracking_visits', function (Blueprint $table) {
            $table->dropIndex(['ip_country_code']);
            $table->dropIndex(['ip_city']);
            $table->dropIndex(['ip_isp']);
            $table->dropColumn([
                'ip_country_code',
                'ip_country_name',
                'ip_region_name',
                'ip_city',
                'ip_postal_code',
                'ip_lat',
                'ip_lng',
                'ip_timezone',
                'ip_asn',
                'ip_isp',
            ]);
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['isp']);
            $table->dropColumn([
                'postal_code',
                'latitude',
                'longitude',
                'timezone',
                'asn',
                'isp',
            ]);
        });

        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropColumn([
                'ip_postal_code',
                'ip_timezone',
                'ip_asn',
                'ip_isp',
            ]);
        });

        Schema::table('user_login_logs', function (Blueprint $table) {
            $table->dropColumn([
                'postal_code',
                'latitude',
                'longitude',
                'timezone',
                'asn',
                'isp',
            ]);
        });
    }
};
