<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('receipt_notify_attempts')->default(0)->after('receipt_last_error');
            $table->unsignedTinyInteger('sheet_notify_attempts')->default(0)->after('sheet_logged_at');
            $table->unsignedTinyInteger('whatsapp_notify_attempts')->default(0)->after('whatsapp_sent_at');
            $table->unsignedTinyInteger('certificate_whatsapp_notify_attempts')->default(0)->after('certificate_whatsapp_sent_at');
            $table->timestamp('whatsapp_failed_at')->nullable()->after('whatsapp_notify_attempts');
            $table->text('whatsapp_last_error')->nullable()->after('whatsapp_failed_at');
            $table->timestamp('certificate_whatsapp_failed_at')->nullable()->after('certificate_whatsapp_notify_attempts');
            $table->text('certificate_whatsapp_last_error')->nullable()->after('certificate_whatsapp_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_notify_attempts',
                'sheet_notify_attempts',
                'whatsapp_notify_attempts',
                'certificate_whatsapp_notify_attempts',
                'whatsapp_failed_at',
                'whatsapp_last_error',
                'certificate_whatsapp_failed_at',
                'certificate_whatsapp_last_error',
            ]);
        });
    }
};
