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
        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30);
            $table->date('date_of_birth')->nullable();
            $table->string('pan_number')->nullable();
            $table->text('address')->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('country', 120)->default('INDIA');
            $table->string('country_code', 3)->default('IN');
            $table->boolean('consent_indian_citizen')->default(false);
            $table->timestamp('last_donated_at')->nullable();
            $table->timestamps();

            $table->unique(['email', 'phone'], 'donors_email_phone_unique');
            $table->index('email', 'donors_email_index');
            $table->index('phone', 'donors_phone_index');
            $table->index('last_donated_at', 'donors_last_donated_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donors');
    }
};
