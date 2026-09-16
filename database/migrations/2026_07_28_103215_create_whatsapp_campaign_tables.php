<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_campaign_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('aisensy_account_id')->constrained('aisensy_accounts')->cascadeOnDelete();
            $table->foreignId('aisensy_wa_template_id')->nullable()->constrained('aisensy_wa_templates')->nullOnDelete();
            $table->string('name');
            $table->string('live_campaign_name');
            $table->string('status', 20)->default('draft');
            $table->json('filters_json')->nullable();
            $table->json('param_map_json')->nullable();
            $table->unsignedInteger('audience_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_campaign_run_id')->constrained('whatsapp_campaign_runs')->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained('donors')->cascadeOnDelete();
            $table->string('phone', 32);
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['whatsapp_campaign_run_id', 'donor_id'], 'wa_campaign_recipients_run_donor_unique');
            $table->index(['whatsapp_campaign_run_id', 'status'], 'wa_campaign_recipients_run_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_recipients');
        Schema::dropIfExists('whatsapp_campaign_runs');
    }
};
