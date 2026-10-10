<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_capi_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_pixel_id')->constrained('meta_pixels')->cascadeOnDelete();
            $table->foreignId('donation_order_id')->nullable()->constrained('donation_orders')->nullOnDelete();
            $table->string('event_name', 64);
            $table->string('event_id', 128);
            $table->string('status', 16);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->json('response_json')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['meta_pixel_id', 'event_id']);
            $table->index(['donation_order_id', 'event_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_capi_event_logs');
    }
};
