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
        Schema::table('donation_items', function (Blueprint $table) {
            $table->foreignId('cause_id')
                ->nullable()
                ->after('donation_order_id')
                ->constrained('causes')
                ->nullOnDelete();

            $table->foreignId('cause_package_id')
                ->nullable()
                ->after('cause_id')
                ->constrained('cause_packages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cause_package_id');
            $table->dropConstrainedForeignId('cause_id');
        });
    }
};
