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
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->foreignId('donor_id')
                ->nullable()
                ->after('provider_payment_id')
                ->constrained('donors')
                ->nullOnDelete();

            $table->index('donor_id', 'donation_orders_donor_id_index');
        });

        DB::table('donation_orders')
            ->orderBy('id')
            ->select([
                'id',
                'donor_name',
                'donor_email',
                'donor_phone',
                'date_of_birth',
                'pan_number',
                'address',
                'pincode',
                'city',
                'state',
                'country',
                'donor_country_code',
                'consent_indian_citizen',
                'created_at',
            ])
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    $email = mb_strtolower(trim((string) $order->donor_email));
                    $phone = trim((string) $order->donor_phone);

                    if ($email === '' || $phone === '') {
                        continue;
                    }

                    $donorRecord = DB::table('donors')
                        ->where('email', $email)
                        ->where('phone', $phone)
                        ->first();

                    $payload = [
                        'name' => (string) $order->donor_name,
                        'date_of_birth' => $order->date_of_birth,
                        'pan_number' => $order->pan_number,
                        'address' => $order->address,
                        'pincode' => $order->pincode,
                        'city' => $order->city,
                        'state' => $order->state,
                        'country' => (string) ($order->country ?: 'INDIA'),
                        'country_code' => (string) ($order->donor_country_code ?: 'IN'),
                        'consent_indian_citizen' => (bool) $order->consent_indian_citizen,
                        'last_donated_at' => $order->created_at,
                        'updated_at' => now(),
                    ];

                    if (! $donorRecord) {
                        $payload['email'] = $email;
                        $payload['phone'] = $phone;
                        $payload['created_at'] = now();

                        $donorId = DB::table('donors')->insertGetId($payload);
                    } else {
                        $donorId = $donorRecord->id;

                        DB::table('donors')
                            ->where('id', $donorId)
                            ->update($payload);
                    }

                    DB::table('donation_orders')
                        ->where('id', $order->id)
                        ->update([
                            'donor_id' => $donorId,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_orders', function (Blueprint $table) {
            $table->dropIndex('donation_orders_donor_id_index');
            $table->dropConstrainedForeignId('donor_id');
        });
    }
};
