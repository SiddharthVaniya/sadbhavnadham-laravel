<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('device_fingerprint', 128)->nullable()->after('remember_token');
            $table->timestamp('device_fingerprint_bound_at')->nullable()->after('device_fingerprint');
        });

        Schema::create('user_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('fingerprint', 128)->nullable();
            $table->boolean('fingerprint_matched')->nullable();
            $table->string('status', 40);
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('country', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('location', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['device_fingerprint', 'device_fingerprint_bound_at']);
        });
    }
};
