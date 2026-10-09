<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('app_id');
            $table->text('app_secret');
            $table->text('access_token');
            $table->string('ad_account_id');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 32)->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'ad_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_accounts');
    }
};
