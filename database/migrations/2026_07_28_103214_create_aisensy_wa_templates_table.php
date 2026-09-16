<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aisensy_wa_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aisensy_account_id')->constrained('aisensy_accounts')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('language', 20)->nullable();
            $table->string('status', 40)->default('APPROVED');
            $table->string('category', 40)->nullable();
            $table->text('body_preview')->nullable();
            $table->unsignedTinyInteger('param_count')->default(0);
            $table->json('components_json')->nullable();
            $table->string('live_campaign_name')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['aisensy_account_id', 'external_id'], 'aisensy_wa_templates_account_external_unique');
            $table->index(['aisensy_account_id', 'status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aisensy_wa_templates');
    }
};
