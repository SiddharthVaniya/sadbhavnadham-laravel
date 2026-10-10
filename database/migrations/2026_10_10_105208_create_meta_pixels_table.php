<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_pixels', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('pixel_id', 32);
            $table->text('access_token');
            $table->boolean('is_active')->default(true);
            $table->boolean('send_purchase')->default(true);
            $table->boolean('send_initiate_checkout')->default(true);
            $table->string('test_event_code', 64)->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->string('last_event_status', 32)->nullable();
            $table->string('last_event_error', 1000)->nullable();
            $table->timestamps();

            $table->unique('pixel_id');
            $table->index(['is_active', 'pixel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_pixels');
    }
};
