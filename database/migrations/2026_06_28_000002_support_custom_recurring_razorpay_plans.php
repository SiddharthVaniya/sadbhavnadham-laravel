<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('razorpay_plans', function (Blueprint $table) {
            $table->foreignId('cause_id')->nullable()->after('id')->constrained('causes')->cascadeOnDelete();
        });

        Schema::table('razorpay_plans', function (Blueprint $table) {
            $table->dropForeign(['cause_package_id']);
            $table->dropUnique('razorpay_plans_package_frequency_unique');
        });

        Schema::table('razorpay_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('cause_package_id')->nullable()->change();
            $table->foreign('cause_package_id')->references('id')->on('cause_packages')->nullOnDelete();
            $table->unique(['cause_package_id', 'frequency'], 'razorpay_plans_package_frequency_unique');
            $table->unique(['cause_id', 'frequency', 'amount'], 'razorpay_plans_custom_amount_unique');
        });
    }

    public function down(): void
    {
        Schema::table('razorpay_plans', function (Blueprint $table) {
            $table->dropUnique('razorpay_plans_custom_amount_unique');
            $table->dropForeign(['cause_package_id']);
            $table->dropUnique('razorpay_plans_package_frequency_unique');
        });

        Schema::table('razorpay_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('cause_package_id')->nullable(false)->change();
            $table->foreign('cause_package_id')->references('id')->on('cause_packages')->cascadeOnDelete();
            $table->unique(['cause_package_id', 'frequency'], 'razorpay_plans_package_frequency_unique');
            $table->dropConstrainedForeignId('cause_id');
        });
    }
};
