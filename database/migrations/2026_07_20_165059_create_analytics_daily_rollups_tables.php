<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('scope', 8);
            $table->unsignedInteger('home_visits')->default(0);
            $table->unsignedInteger('cause_views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('unique_cause_visitors')->default(0);
            $table->unsignedInteger('checkouts_started')->default(0);
            $table->unsignedInteger('donations_paid')->default(0);
            $table->unsignedInteger('donations_failed')->default(0);
            $table->decimal('tracked_revenue', 14, 2)->default(0);
            $table->unsignedInteger('countries_reached')->default(0);
            $table->timestamps();

            $table->unique(['stat_date', 'scope']);
            $table->index(['scope', 'stat_date']);
        });

        Schema::create('analytics_daily_causes', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('scope', 8);
            $table->unsignedBigInteger('cause_id');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('checkouts')->default(0);
            $table->unsignedInteger('paid')->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['stat_date', 'scope', 'cause_id']);
            $table->index(['scope', 'stat_date']);
            $table->index(['cause_id', 'stat_date']);
        });

        Schema::create('analytics_daily_dimensions', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('scope', 8);
            $table->string('dimension_type', 32);
            $table->string('dimension_key', 512);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->timestamps();

            $table->unique(['stat_date', 'scope', 'dimension_type', 'dimension_key'], 'analytics_daily_dims_unique');
            $table->index(['scope', 'dimension_type', 'stat_date'], 'analytics_daily_dims_lookup');
        });

        Schema::create('analytics_session_days', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('scope', 8);
            $table->uuid('session_id');
            $table->boolean('visited_cause')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['stat_date', 'scope', 'session_id'], 'analytics_session_days_unique');
            $table->index(['scope', 'stat_date', 'visited_cause'], 'analytics_session_days_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_session_days');
        Schema::dropIfExists('analytics_daily_dimensions');
        Schema::dropIfExists('analytics_daily_causes');
        Schema::dropIfExists('analytics_daily_stats');
    }
};
