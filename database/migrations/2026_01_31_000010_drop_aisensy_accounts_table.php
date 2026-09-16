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
        if (Schema::hasTable('aisensy_accounts')) {
            Schema::drop('aisensy_accounts');
        }

        if (Schema::hasColumn('causes', 'aisensy_account_id')) {
            Schema::table('causes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('aisensy_account_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('aisensy_accounts')) {
            Schema::create('aisensy_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('api_key');
                $table->string('country_code', 10)->default('91');
                $table->string('payment_link_campaign')->nullable();
                $table->string('thank_you_general_campaign')->nullable();
                $table->string('thank_you_tree_campaign')->nullable();
                $table->string('thank_you_general_image')->nullable();
                $table->string('thank_you_tree_image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('causes', 'aisensy_account_id')) {
            Schema::table('causes', function (Blueprint $table) {
                $table->foreignId('aisensy_account_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('aisensy_accounts')
                    ->nullOnDelete();
            });
        }
    }
};
