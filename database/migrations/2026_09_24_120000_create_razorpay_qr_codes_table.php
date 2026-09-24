<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('razorpay_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('qr_uuid')->unique();
            $table->string('razorpay_qr_code_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 32)->default('upi_qr');
            $table->string('usage', 32)->default('multiple_use');
            $table->boolean('fixed_amount')->default(false);
            $table->unsignedBigInteger('payment_amount_paise')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->string('image_url', 500)->nullable();
            $table->unsignedInteger('payments_count_received')->default(0);
            $table->unsignedBigInteger('payments_amount_received_paise')->default(0);
            $table->string('close_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('razorpay_created_at')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_qr_codes');
    }
};
