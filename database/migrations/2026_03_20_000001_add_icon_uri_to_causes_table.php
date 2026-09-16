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
            $table->string('icon_uri')->nullable()->after('slug');
            if (Schema::hasColumn('causes', 'icon_class')) {
                $table->dropColumn('icon_class');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->string('icon_class')->nullable()->after('slug');
            if (Schema::hasColumn('causes', 'icon_uri')) {
                $table->dropColumn('icon_uri');
            }
        });
    }
};