<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birthday_message_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('aisensy_account_id', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('birthday_message_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('days_before');
            $table->string('kind', 32); // marketing | warm_wish
            $table->boolean('enabled')->default(true);
            $table->string('campaign_name')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['days_before', 'kind']);
        });

        Schema::create('birthday_message_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->constrained('donors')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->foreignId('birthday_message_step_id')->nullable()->constrained('birthday_message_steps')->nullOnDelete();
            $table->unsignedSmallInteger('days_before');
            $table->string('kind', 32);
            $table->string('campaign_name')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['donor_id', 'year', 'days_before', 'kind'], 'birthday_sends_donor_year_offset_kind_unique');
            $table->index(['year', 'days_before', 'kind']);
        });

        $now = now();

        DB::table('birthday_message_settings')->insert([
            'enabled' => (bool) ((int) (DB::table('settings')->where('key', 'send_birthday_whatsapp')->value('value') ?? 0)),
            'aisensy_account_id' => DB::table('settings')->where('key', 'aisensy_birthday_account_id')->value('value') ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Default steps are inserted by ops SQL / admin UI — not auto-seeded here so tests stay isolated.
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_message_sends');
        Schema::dropIfExists('birthday_message_steps');
        Schema::dropIfExists('birthday_message_settings');
    }
};
