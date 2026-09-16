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
        Schema::create('donation_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_uuid')->unique();
            $table->string('payment_provider', 50);
            $table->string('provider_order_id')->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('donor_name');
            $table->string('donor_email');
            $table->string('donor_phone');
            $table->string('pan_number')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('total_amount', 10, 2);
            $table->string('status', 30)->default('pending');
            $table->string('receipt_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_orders');
    }
};
