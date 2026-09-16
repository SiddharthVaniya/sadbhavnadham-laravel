<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('device_type');
            $table->string('ip_country_code', 2)->nullable()->after('ip_address');
            $table->string('ip_country_name', 100)->nullable()->after('ip_country_code');
            $table->string('ip_region_name', 100)->nullable()->after('ip_country_name');
            $table->string('ip_city', 100)->nullable()->after('ip_region_name');
            $table->decimal('ip_lat', 10, 7)->nullable()->after('ip_city');
            $table->decimal('ip_lng', 10, 7)->nullable()->after('ip_lat');

            $table->index('ip_country_code');
            $table->index('ip_city');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropIndex(['ip_country_code']);
            $table->dropIndex(['ip_city']);
            $table->dropColumn([
                'ip_address',
                'ip_country_code',
                'ip_country_name',
                'ip_region_name',
                'ip_city',
                'ip_lat',
                'ip_lng',
            ]);
        });
    }
};
