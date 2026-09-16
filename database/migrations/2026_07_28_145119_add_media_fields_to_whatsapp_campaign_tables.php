<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aisensy_wa_templates', function (Blueprint $table) {
            $table->string('header_type', 40)->nullable()->after('category');
        });

        Schema::table('whatsapp_campaign_runs', function (Blueprint $table) {
            $table->string('media_path')->nullable()->after('param_map_json');
            $table->string('media_filename')->nullable()->after('media_path');
        });
    }

    public function down(): void
    {
        Schema::table('aisensy_wa_templates', function (Blueprint $table) {
            $table->dropColumn('header_type');
        });

        Schema::table('whatsapp_campaign_runs', function (Blueprint $table) {
            $table->dropColumn(['media_path', 'media_filename']);
        });
    }
};
