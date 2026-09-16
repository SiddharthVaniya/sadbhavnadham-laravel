<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('razorpay_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cause_package_id')->constrained('cause_packages')->cascadeOnDelete();
            $table->string('frequency', 20);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('INR');
            $table->string('razorpay_plan_id')->unique();
            $table->string('plan_name');
            $table->timestamps();

            $table->unique(['cause_package_id', 'frequency'], 'razorpay_plans_package_frequency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_plans');
    }
};
