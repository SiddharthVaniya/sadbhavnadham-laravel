<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->string('contact_heading')->nullable()->after('cta_text');
            $table->text('contact_address')->nullable()->after('contact_heading');
            $table->string('contact_phone')->nullable()->after('contact_address');
            $table->string('contact_email')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn([
                'contact_heading',
                'contact_address',
                'contact_phone',
                'contact_email',
            ]);
        });
    }
};
