<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aisensy_accounts', function (Blueprint $table) {
            $table->text('project_api_password')->nullable()->after('api_key');
            $table->string('project_id')->nullable()->after('project_api_password');
        });
    }

    public function down(): void
    {
        Schema::table('aisensy_accounts', function (Blueprint $table) {
            $table->dropColumn(['project_api_password', 'project_id']);
        });
    }
};
