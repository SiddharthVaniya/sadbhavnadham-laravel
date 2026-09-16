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
            $table->index(['created_at', 'id'], 'donation_orders_created_at_id_index');
            $table->index(['status', 'created_at'], 'donation_orders_status_created_at_index');
            $table->index(['payment_provider', 'created_at'], 'donation_orders_provider_created_at_index');

            $table->index('paid_at', 'donation_orders_paid_at_index');
            $table->index('donor_email', 'donation_orders_donor_email_index');
            $table->index('donor_phone', 'donation_orders_donor_phone_index');
            $table->index('provider_order_id', 'donation_orders_provider_order_id_index');
            $table->index('provider_payment_id', 'donation_orders_provider_payment_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropIndex('donation_orders_created_at_id_index');
            $table->dropIndex('donation_orders_status_created_at_index');
            $table->dropIndex('donation_orders_provider_created_at_index');

            $table->dropIndex('donation_orders_paid_at_index');
            $table->dropIndex('donation_orders_donor_email_index');
            $table->dropIndex('donation_orders_donor_phone_index');
            $table->dropIndex('donation_orders_provider_order_id_index');
            $table->dropIndex('donation_orders_provider_payment_id_index');
        });
    }
};
