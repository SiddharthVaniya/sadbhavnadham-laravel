<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('subscription_uuid')->unique();
            $table->foreignId('donor_id')->constrained('donors')->cascadeOnDelete();
            $table->foreignId('cause_id')->constrained('causes')->restrictOnDelete();
            $table->foreignId('cause_package_id')->nullable()->constrained('cause_packages')->nullOnDelete();
            $table->string('frequency', 20);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 10)->default('INR');
            $table->string('item_title');
            $table->string('razorpay_plan_id')->nullable();
            $table->string('razorpay_subscription_id')->nullable()->unique();
            $table->string('status', 30)->default('created');
            $table->unsignedInteger('billing_cycle_count')->default(0);
            $table->unsignedInteger('total_count')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('next_charge_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->string('donor_name');
            $table->string('donor_email');
            $table->string('donor_phone');
            $table->date('date_of_birth')->nullable();
            $table->string('pan_number')->nullable();
            $table->text('address')->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('country', 120)->default('INDIA');
            $table->string('donor_country_code', 3)->default('IN');
            $table->boolean('consent_indian_citizen')->default(false);
            $table->boolean('consent_recurring')->default(false);

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_charge_at']);
            $table->index(['donor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_subscriptions');
    }
};
