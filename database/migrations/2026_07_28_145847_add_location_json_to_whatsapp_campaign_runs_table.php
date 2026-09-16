<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaign_runs', function (Blueprint $table) {
            $table->json('location_json')->nullable()->after('media_filename');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaign_runs', function (Blueprint $table) {
            $table->dropColumn('location_json');
        });
    }
};
