<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_device_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 128);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
            $table->index('fingerprint');
        });

        if (Schema::hasColumn('users', 'device_fingerprint')) {
            $rows = DB::table('users')
                ->whereNotNull('device_fingerprint')
                ->where('device_fingerprint', '!=', '')
                ->get(['id', 'device_fingerprint', 'device_fingerprint_bound_at']);

            foreach ($rows as $row) {
                DB::table('user_device_fingerprints')->insertOrIgnore([
                    'user_id' => $row->id,
                    'fingerprint' => $row->device_fingerprint,
                    'last_used_at' => $row->device_fingerprint_bound_at,
                    'created_at' => $row->device_fingerprint_bound_at ?? now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_fingerprints');
    }
};
