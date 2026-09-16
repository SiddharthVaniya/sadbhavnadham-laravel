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
            $table->string('aisensy_thank_you_message_mode')
                ->default('template')
                ->after('aisensy_thank_you_image');
            $table->text('aisensy_thank_you_message_template')
                ->nullable()
                ->after('aisensy_thank_you_message_mode');
            $table->boolean('aisensy_thank_you_include_name')
                ->default(true)
                ->after('aisensy_thank_you_message_template');
            $table->boolean('aisensy_thank_you_include_amount')
                ->default(true)
                ->after('aisensy_thank_you_include_name');
            $table->boolean('aisensy_thank_you_include_cause')
                ->default(true)
                ->after('aisensy_thank_you_include_amount');
            $table->boolean('aisensy_thank_you_include_receipt')
                ->default(false)
                ->after('aisensy_thank_you_include_cause');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn([
                'aisensy_thank_you_message_mode',
                'aisensy_thank_you_message_template',
                'aisensy_thank_you_include_name',
                'aisensy_thank_you_include_amount',
                'aisensy_thank_you_include_cause',
                'aisensy_thank_you_include_receipt',
            ]);
        });
    }
};
