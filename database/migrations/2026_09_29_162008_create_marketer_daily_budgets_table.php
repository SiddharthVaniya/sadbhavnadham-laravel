<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_daily_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('spend_date');
            $table->decimal('limit_amount', 14, 2)->nullable();
            $table->decimal('spend_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'spend_date'], 'marketer_daily_budgets_user_date_unique');
            $table->index('spend_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_daily_budgets');
    }
};
