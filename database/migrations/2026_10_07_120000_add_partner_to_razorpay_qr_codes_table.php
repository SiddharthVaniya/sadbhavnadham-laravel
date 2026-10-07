<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('razorpay_qr_codes', function (Blueprint $table) {
            $table->foreignId('partner_user_id')
                ->nullable()
                ->after('cause_package_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('partner_code', 40)
                ->nullable()
                ->after('partner_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('razorpay_qr_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_user_id');
            $table->dropColumn('partner_code');
        });
    }
};
