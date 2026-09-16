<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('link_tracking_visits')) {
            Schema::create('link_tracking_visits', function (Blueprint $table) {
                $table->id();
                $table->char('visitor_id', 36);
                $table->string('sid', 255)->default('');
                $table->string('utm_source', 120)->nullable();
                $table->string('utm_medium', 120)->nullable();
                $table->string('utm_campaign', 120)->nullable();
                $table->string('utm_content', 120)->nullable();
                $table->string('utm_id', 120)->nullable();
                $table->string('utm_term', 120)->nullable();
                $table->string('fbclid', 255)->nullable();
                $table->string('amt', 32)->nullable();
                $table->string('ptype', 32)->nullable();
                $table->string('landing_url', 2048)->nullable();
                $table->string('referrer', 512)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->string('device_type', 16)->nullable();
                $table->boolean('is_unique')->default(true);
                $table->boolean('converted')->default(false);
                $table->unsignedBigInteger('donation_order_id')->nullable();
                $table->decimal('converted_amount', 12, 2)->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();

                $table->unique(['visitor_id', 'sid'], 'uq_visitor_sid');
                $table->index('sid', 'idx_sid');
                $table->index('visitor_id', 'idx_visitor_id');
                $table->index('converted', 'idx_converted');
                $table->index('donation_order_id', 'idx_donation_order');
                $table->index('created_at', 'idx_created_at');
                $table->index('utm_source', 'idx_utm_source');
                $table->index('utm_campaign', 'idx_utm_campaign');
            });
        }

        if (! Schema::hasTable('link_tracking_summary')) {
            Schema::create('link_tracking_summary', function (Blueprint $table) {
                $table->id();
                $table->string('sid', 255);
                $table->unsignedInteger('total_clicks')->default(0);
                $table->unsignedInteger('unique_visitors')->default(0);
                $table->unsignedInteger('total_donations')->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('average_amount', 12, 2)->default(0);
                $table->timestamp('first_click_at')->nullable();
                $table->timestamp('last_click_at')->nullable();
                $table->timestamp('last_donation_at')->nullable();
                $table->string('utm_source', 120)->nullable();
                $table->string('utm_medium', 120)->nullable();
                $table->string('utm_campaign', 120)->nullable();
                $table->timestamps();

                $table->unique('sid', 'uq_sid');
                $table->index('total_amount', 'idx_total_amount');
                $table->index('last_click_at', 'idx_last_click');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_tracking_visits');
        Schema::dropIfExists('link_tracking_summary');
    }
};
