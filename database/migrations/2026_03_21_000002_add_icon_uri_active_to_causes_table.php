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
            if (! Schema::hasColumn('causes', 'icon_uri_active')) {
                $table->string('icon_uri_active')->nullable()->after('icon_uri');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            if (Schema::hasColumn('causes', 'icon_uri_active')) {
                $table->dropColumn('icon_uri_active');
            }
        });
    }
};
