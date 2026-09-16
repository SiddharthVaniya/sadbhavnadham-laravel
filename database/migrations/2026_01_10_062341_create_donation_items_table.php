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
        Schema::create('donation_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('donation_order_id')->constrained('donation_orders')->cascadeOnDelete();
            $table->string('cause');
            $table->string('title');
            $table->integer('quantity')->nullable();
            $table->decimal('unit_amount', 10, 2)->nullable();
            $table->decimal('amount', 10, 2);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_items');
    }
};
