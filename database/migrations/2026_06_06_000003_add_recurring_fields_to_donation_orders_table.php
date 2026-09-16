<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->foreignId('donation_subscription_id')
                ->nullable()
                ->after('donor_id')
                ->constrained('donation_subscriptions')
                ->nullOnDelete();
            $table->unsignedSmallInteger('billing_cycle_number')->nullable()->after('donation_subscription_id');
            $table->boolean('is_recurring')->default(false)->after('billing_cycle_number');

            $table->index(['donation_subscription_id', 'billing_cycle_number'], 'donation_orders_subscription_cycle_index');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropIndex('donation_orders_subscription_cycle_index');
            $table->dropConstrainedForeignId('donation_subscription_id');
            $table->dropColumn(['billing_cycle_number', 'is_recurring']);
        });
    }
};
