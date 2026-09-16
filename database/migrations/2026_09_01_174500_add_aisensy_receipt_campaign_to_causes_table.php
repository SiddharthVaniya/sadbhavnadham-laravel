<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->string('aisensy_receipt_campaign')->nullable()->after('aisensy_certificate_campaign');
            $table->boolean('aisensy_send_receipt')->default(true)->after('aisensy_send_certificate');
        });
    }

    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn(['aisensy_receipt_campaign', 'aisensy_send_receipt']);
        });
    }
};
