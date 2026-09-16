<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipt_number_sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
        });

        $highestReceiptNumber = DB::table('donation_orders')
            ->whereNotNull('receipt_number')
            ->pluck('receipt_number')
            ->map(fn (string $receiptNumber): int => (int) $receiptNumber)
            ->max() ?? 0;

        $nextReceiptNumber = $highestReceiptNumber + 1;

        DB::table('receipt_number_sequences')->insert([
            'name' => 'donation_orders',
            'next_number' => $nextReceiptNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_number_sequences');
    }
};
