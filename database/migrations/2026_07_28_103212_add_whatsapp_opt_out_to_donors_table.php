<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->boolean('whatsapp_opt_out')->default(false)->after('consent_indian_citizen');
            $table->timestamp('whatsapp_opted_out_at')->nullable()->after('whatsapp_opt_out');
        });
    }

    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_opt_out', 'whatsapp_opted_out_at']);
        });
    }
};
