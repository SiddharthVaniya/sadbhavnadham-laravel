<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->boolean('allow_recurring')->default(false)->after('allow_custom_amount');
        });
    }

    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn('allow_recurring');
        });
    }
};
