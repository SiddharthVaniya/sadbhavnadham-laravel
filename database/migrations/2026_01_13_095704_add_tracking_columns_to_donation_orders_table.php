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

            $table->timestamp('paid_at')->nullable()->after('status');
            $table->timestamp('failed_at')->nullable()->after('paid_at');

            $table->timestamp('receipt_sent_at')->nullable()->after('failed_at');
            $table->timestamp('sheet_logged_at')->nullable()->after('receipt_sent_at');
            $table->timestamp('whatsapp_sent_at')->nullable()->after('sheet_logged_at');

            $table->string('payment_link_id')->nullable()->after('whatsapp_sent_at');
            $table->string('payment_link_url')->nullable()->after('payment_link_id');
            $table->timestamp('payment_link_sent_at')->nullable()->after('payment_link_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
           $table->dropColumn([
                'paid_at',
                'failed_at',
                'receipt_sent_at',
                'sheet_logged_at',
                'whatsapp_sent_at',
                'payment_link_id',
                'payment_link_url',
                'payment_link_sent_at',
            ]);
        });
    }
};
