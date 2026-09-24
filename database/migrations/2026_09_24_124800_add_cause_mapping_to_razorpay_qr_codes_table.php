<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('razorpay_qr_codes', function (Blueprint $table) {
            $table->foreignId('cause_id')
                ->nullable()
                ->after('created_by')
                ->constrained('causes')
                ->nullOnDelete();
            $table->foreignId('cause_package_id')
                ->nullable()
                ->after('cause_id')
                ->constrained('cause_packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('razorpay_qr_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cause_package_id');
            $table->dropConstrainedForeignId('cause_id');
        });
    }
};
