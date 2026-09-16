<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->boolean('aisensy_send_thank_you')->default(true)->after('aisensy_certificate_campaign');
            $table->boolean('aisensy_send_certificate')->default(true)->after('aisensy_send_thank_you');
        });
    }

    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn(['aisensy_send_thank_you', 'aisensy_send_certificate']);
        });
    }
};
