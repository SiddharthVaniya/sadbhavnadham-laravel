<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->timestamp('payment_link_email_sent_at')->nullable()->after('payment_link_sent_at');
            $table->timestamp('payment_link_sms_sent_at')->nullable()->after('payment_link_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_link_email_sent_at',
                'payment_link_sms_sent_at',
            ]);
        });
    }
};
