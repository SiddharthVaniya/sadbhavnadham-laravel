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
        Schema::create('causes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('excerpt')->nullable();
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->string('hero_image')->nullable();
            $table->json('seva_details')->nullable();
            $table->boolean('allow_custom_amount')->default(true);
            $table->boolean('pan_required')->default(true);
            $table->decimal('default_amount', 10, 2)->nullable();
            $table->string('default_title')->nullable();
            $table->string('cta_text')->nullable();
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
        Schema::dropIfExists('causes');
    }
};
