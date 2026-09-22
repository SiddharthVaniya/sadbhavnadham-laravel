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
        Schema::table('causes', function (Blueprint $table) {
            $table->foreignId('aisensy_account_id')
                ->nullable()
                ->after('cta_text')
                ->constrained('aisensy_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->dropForeign(['aisensy_account_id']);
            $table->dropColumn('aisensy_account_id');
        });
    }
};
