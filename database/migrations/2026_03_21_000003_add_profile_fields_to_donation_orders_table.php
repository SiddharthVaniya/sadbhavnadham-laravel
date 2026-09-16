<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('donor_phone');
            $table->string('pincode', 10)->nullable()->after('address');
            $table->string('city', 120)->nullable()->after('pincode');
            $table->string('state', 120)->nullable()->after('city');
            $table->string('country', 120)->default('INDIA')->after('state');
            $table->string('donor_country_code', 3)->default('IN')->after('country');
            $table->boolean('consent_indian_citizen')->default(false)->after('donor_country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'pincode',
                'city',
                'state',
                'country',
                'donor_country_code',
                'consent_indian_citizen',
            ]);
        });
    }
};
