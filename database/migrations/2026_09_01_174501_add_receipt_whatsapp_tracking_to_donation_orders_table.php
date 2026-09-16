<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->timestamp('receipt_whatsapp_sent_at')->nullable()->after('certificate_whatsapp_sent_at');
            $table->unsignedTinyInteger('receipt_whatsapp_notify_attempts')->default(0)->after('receipt_whatsapp_sent_at');
            $table->timestamp('receipt_whatsapp_failed_at')->nullable()->after('receipt_whatsapp_notify_attempts');
            $table->text('receipt_whatsapp_last_error')->nullable()->after('receipt_whatsapp_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_whatsapp_sent_at',
                'receipt_whatsapp_notify_attempts',
                'receipt_whatsapp_failed_at',
                'receipt_whatsapp_last_error',
            ]);
        });
    }
};
