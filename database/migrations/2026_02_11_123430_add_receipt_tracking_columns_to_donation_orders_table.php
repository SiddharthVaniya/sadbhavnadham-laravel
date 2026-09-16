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
           $table->string('receipt_path')->nullable()
                ->after('receipt_number');

            $table->timestamp('receipt_failed_at')->nullable()
                ->after('receipt_sent_at');

            $table->text('receipt_last_error')->nullable()
                ->after('receipt_failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_path',
                'receipt_failed_at',
                'receipt_last_error',
            ]);
        });
    }
};
