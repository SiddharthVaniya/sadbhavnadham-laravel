<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_monthly_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('year_month', 7);
            $table->unsignedInteger('target_amount')->nullable();
            $table->decimal('spend_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'year_month'], 'marketer_monthly_budgets_user_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_monthly_budgets');
    }
};
