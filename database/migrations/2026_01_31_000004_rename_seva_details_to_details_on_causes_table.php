<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->json('details')->nullable()->after('hero_image');
        });

        DB::table('causes')->update([
            'details' => DB::raw('seva_details'),
        ]);

        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn('seva_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->json('seva_details')->nullable()->after('hero_image');
        });

        DB::table('causes')->update([
            'seva_details' => DB::raw('details'),
        ]);

        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
