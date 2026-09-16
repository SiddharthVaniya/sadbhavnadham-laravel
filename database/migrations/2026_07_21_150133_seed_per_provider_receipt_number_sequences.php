<?php

use App\Models\DonationOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $families = [
            DonationOrder::PROVIDER_RAZORPAY,
            DonationOrder::PROVIDER_OFFLINE,
            DonationOrder::PROVIDER_DANAMOJO,
            DonationOrder::PROVIDER_CASHFREE,
        ];

        $legacyNext = (int) (DB::table('receipt_number_sequences')
            ->where('name', 'donation_orders')
            ->value('next_number') ?? 0);

        foreach ($families as $family) {
            $providers = DonationOrder::receiptProviderFamilyMembers($family);

            $highest = DB::table('donation_orders')
                ->whereNotNull('receipt_number')
                ->whereIn('payment_provider', $providers)
                ->pluck('receipt_number')
                ->map(fn (string $receiptNumber): int => (int) $receiptNumber)
                ->max() ?? 0;

            $nextNumber = $highest + 1;

            if ($family === DonationOrder::PROVIDER_RAZORPAY && $legacyNext > 0) {
                $nextNumber = max($nextNumber, $legacyNext);
            }

            $sequenceName = DonationOrder::receiptSequenceName($family);

            DB::table('receipt_number_sequences')->updateOrInsert(
                ['name' => $sequenceName],
                [
                    'next_number' => $nextNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        DB::table('receipt_number_sequences')
            ->where('name', 'donation_orders')
            ->delete();
    }

    public function down(): void
    {
        $razorpayNext = (int) (DB::table('receipt_number_sequences')
            ->where('name', DonationOrder::receiptSequenceName(DonationOrder::PROVIDER_RAZORPAY))
            ->value('next_number') ?? 1);

        DB::table('receipt_number_sequences')->updateOrInsert(
            ['name' => 'donation_orders'],
            [
                'next_number' => max(1, $razorpayNext),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('receipt_number_sequences')
            ->whereIn('name', [
                DonationOrder::receiptSequenceName(DonationOrder::PROVIDER_RAZORPAY),
                DonationOrder::receiptSequenceName(DonationOrder::PROVIDER_OFFLINE),
                DonationOrder::receiptSequenceName(DonationOrder::PROVIDER_DANAMOJO),
                DonationOrder::receiptSequenceName(DonationOrder::PROVIDER_CASHFREE),
            ])
            ->delete();
    }
};
