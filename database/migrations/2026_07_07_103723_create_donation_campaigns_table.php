<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('cause_id')->constrained('causes')->cascadeOnDelete();
            $table->foreignId('cause_package_id')->nullable()->constrained('cause_packages')->nullOnDelete();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('title')->nullable();
            $table->string('headline')->nullable();
            $table->text('subheadline')->nullable();
            $table->boolean('recurring_only')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_campaigns');
    }
};
