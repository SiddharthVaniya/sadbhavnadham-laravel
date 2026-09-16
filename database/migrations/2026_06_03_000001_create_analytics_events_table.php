<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 40);
            $table->uuid('session_id')->nullable();
            $table->unsignedBigInteger('cause_id')->nullable();
            $table->unsignedBigInteger('donation_order_id')->nullable();
            $table->string('path', 255)->nullable();
            $table->string('referrer', 512)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at']);
            $table->index(['session_id', 'created_at']);
            $table->index(['cause_id', 'created_at']);
            $table->index('donation_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
