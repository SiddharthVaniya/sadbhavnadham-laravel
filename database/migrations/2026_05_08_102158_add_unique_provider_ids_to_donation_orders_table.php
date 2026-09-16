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
            $table->unique('provider_order_id', 'donation_orders_provider_order_id_unique');
            $table->unique('provider_payment_id', 'donation_orders_provider_payment_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropUnique('donation_orders_provider_order_id_unique');
            $table->dropUnique('donation_orders_provider_payment_id_unique');
        });
    }
};
