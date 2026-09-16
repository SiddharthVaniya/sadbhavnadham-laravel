<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->default('1');
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        DB::table('settings')->insert([
            [
                'key' => 'send_receipt_email',
                'value' => '1',
                'label' => 'Send Donation Receipt Email',
                'description' => 'When enabled, a receipt email is sent to the donor after a successful payment.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'send_whatsapp_thank_you',
                'value' => '1',
                'label' => 'Send WhatsApp Thank You Message',
                'description' => 'When enabled, a thank you WhatsApp message is sent to the donor after a successful payment.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'send_whatsapp_payment_link',
                'value' => '1',
                'label' => 'Send WhatsApp Payment Link Message',
                'description' => 'When enabled, a WhatsApp message with a payment recovery link is sent to the donor after a failed payment.',
                'group' => 'notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
