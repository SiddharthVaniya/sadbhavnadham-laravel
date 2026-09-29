<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('failed_at');
            $table->string('razorpay_refund_id', 64)->nullable()->after('refunded_at');
            $table->decimal('refund_amount', 12, 2)->nullable()->after('razorpay_refund_id');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_at', 'razorpay_refund_id', 'refund_amount']);
        });
    }
};
