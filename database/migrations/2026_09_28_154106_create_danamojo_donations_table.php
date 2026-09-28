<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('danamojo_donations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('donation_info_id')->unique();
            $table->foreignId('donation_order_id')->nullable()->constrained('donation_orders')->nullOnDelete();
            $table->string('payment_status', 40)->nullable();
            $table->string('dm_status', 40)->nullable();
            $table->string('sync_state', 32)->default('notified');
            $table->string('donor_name')->nullable();
            $table->string('donor_email')->nullable();
            $table->string('donor_phone', 40)->nullable();
            $table->string('nationality', 80)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('currency', 8)->nullable();
            $table->decimal('amount_local', 12, 2)->nullable();
            $table->decimal('amount_inr', 12, 2)->nullable();
            $table->string('payment_option', 40)->nullable();
            $table->string('product_name')->nullable();
            $table->string('receipt_number', 80)->nullable();
            $table->string('receipt_link', 512)->nullable();
            $table->text('referer_url')->nullable();
            $table->text('landing_url')->nullable();
            $table->string('sid', 40)->nullable();
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->string('utm_content', 120)->nullable();
            $table->string('utm_term', 120)->nullable();
            $table->string('utm_id', 40)->nullable();
            $table->string('aid', 40)->nullable();
            $table->string('partner_code', 40)->nullable();
            $table->unsignedBigInteger('partner_user_id')->nullable();
            $table->string('device', 32)->nullable();
            $table->boolean('recurring')->default(false);
            $table->boolean('fcra')->nullable();
            $table->boolean('international')->nullable();
            $table->timestamp('donated_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('notify_payload')->nullable();
            $table->timestamps();

            $table->index('sync_state');
            $table->index('payment_status');
            $table->index('next_retry_at');
            $table->index('partner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('danamojo_donations');
    }
};
