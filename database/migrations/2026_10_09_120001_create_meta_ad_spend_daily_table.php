<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_spend_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->cascadeOnDelete();
            $table->date('spend_date');
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('adset_id')->nullable();
            $table->string('adset_name')->nullable();
            $table->string('ad_id');
            $table->string('ad_name')->nullable();
            $table->decimal('spend_amount', 14, 2)->default(0);
            $table->string('currency', 16)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('matched_via', 32)->default('unmatched');
            $table->timestamps();

            $table->unique(['meta_ad_account_id', 'spend_date', 'ad_id'], 'meta_ad_spend_daily_acct_date_ad_unique');
            $table->index(['spend_date', 'user_id']);
            $table->index('campaign_name');
            $table->index('ad_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_spend_daily');
    }
};
