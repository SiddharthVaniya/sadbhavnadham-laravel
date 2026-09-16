<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cause_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cause_id')->constrained('causes')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->string('image')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cause_packages');
    }
};
