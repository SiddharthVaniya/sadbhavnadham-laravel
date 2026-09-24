<?php

namespace Database\Seeders;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\PaymentEvent;
use App\Models\RazorpayQrCode;
use App\Services\DonationAttributionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DemoRazorpayQrDonationSeeder extends Seeder
{
    /**
     * Local-only fake Razorpay QR donations for admin UI testing (QR name pill).
     *
     * Run: php artisan db:seed --class=DemoRazorpayQrDonationSeeder
     */
    public function run(): void
    {
        $cause = Cause::query()->where('is_active', true)->first()
            ?? Cause::factory()->create([
                'title' => 'Old Age Home',
                'slug' => 'old-age-home-demo-qr',
                'is_active' => true,
            ]);

        $package = CausePackage::query()
            ->where('cause_id', $cause->id)
            ->where('is_active', true)
            ->first()
            ?? CausePackage::factory()->create([
                'cause_id' => $cause->id,
                'title' => 'Demo QR Package',
                'amount' => 501,
                'is_active' => true,
            ]);

        $counterQrPayload = [
            'name' => 'Temple Counter QR',
            'description' => 'Demo counter collection QR',
            'type' => RazorpayQrCode::TYPE_UPI,
            'usage' => RazorpayQrCode::USAGE_MULTIPLE,
            'fixed_amount' => false,
            'status' => RazorpayQrCode::STATUS_ACTIVE,
            'image_url' => 'https://rzp.io/i/demoCounter',
            'payments_count_received' => 2,
            'payments_amount_received_paise' => 160100,
            'razorpay_created_at' => now()->subDays(3),
        ];

        $footerQrPayload = [
            'name' => 'Website Footer QR',
            'description' => 'Demo website footer QR',
            'type' => RazorpayQrCode::TYPE_UPI,
            'usage' => RazorpayQrCode::USAGE_MULTIPLE,
            'fixed_amount' => false,
            'status' => RazorpayQrCode::STATUS_ACTIVE,
            'image_url' => 'https://rzp.io/i/demoFooter',
            'payments_count_received' => 1,
            'payments_amount_received_paise' => 110000,
            'razorpay_created_at' => now()->subDays(10),
        ];

        if (Schema::hasColumn('razorpay_qr_codes', 'cause_id')) {
            $counterQrPayload['cause_id'] = $cause->id;
            $counterQrPayload['cause_package_id'] = $package->id;
            $footerQrPayload['cause_id'] = $cause->id;
            $footerQrPayload['cause_package_id'] = null;
        }

        $counterQr = RazorpayQrCode::query()->updateOrCreate(
            ['razorpay_qr_code_id' => 'qr_DEMO_COUNTER_001'],
            $counterQrPayload
        );

        $footerQr = RazorpayQrCode::query()->updateOrCreate(
            ['razorpay_qr_code_id' => 'qr_DEMO_FOOTER_001'],
            $footerQrPayload
        );

        $scenarios = [
            $this->seedDonation(
                paymentId: 'pay_DEMO_QR_COUNTER_1',
                qr: $counterQr,
                donorName: 'Demo QR Counter Donor',
                donorEmail: 'demo.qr.counter@example.com',
                donorPhone: '9822222201',
                amount: 501,
                cause: $cause,
                package: $package,
                hoursAgo: 2,
            ),
            $this->seedDonation(
                paymentId: 'pay_DEMO_QR_COUNTER_2',
                qr: $counterQr,
                donorName: 'Demo QR Counter Donor 2',
                donorEmail: 'demo.qr.counter2@example.com',
                donorPhone: '9822222202',
                amount: 1100,
                cause: $cause,
                package: $package,
                hoursAgo: 5,
            ),
            $this->seedDonation(
                paymentId: 'pay_DEMO_QR_FOOTER_1',
                qr: $footerQr,
                donorName: 'Demo QR Footer Donor',
                donorEmail: 'demo.qr.footer@example.com',
                donorPhone: '9822222203',
                amount: 1100,
                cause: $cause,
                package: null,
                hoursAgo: 8,
            ),
        ];

        $this->command?->info('Demo Razorpay QR donation records ready:');
        $this->command?->table(
            ['QR name', 'Donor', 'Payment ID', 'Amount', 'Admin donation'],
            collect($scenarios)->map(fn (array $row) => [
                $row['qr_name'],
                $row['donor_name'],
                $row['payment_id'],
                '₹ '.number_format($row['amount'], 2),
                '/admin/donations/'.$row['order_uuid'],
            ])->all()
        );
    }

    /**
     * @return array{qr_name: string, donor_name: string, payment_id: string, amount: float, order_uuid: string}
     */
    private function seedDonation(
        string $paymentId,
        RazorpayQrCode $qr,
        string $donorName,
        string $donorEmail,
        string $donorPhone,
        float $amount,
        Cause $cause,
        ?CausePackage $package,
        int $hoursAgo,
    ): array {
        $donor = Donor::query()->updateOrCreate(
            ['email' => $donorEmail],
            [
                'name' => $donorName,
                'phone' => $donorPhone,
                'city' => 'Rajkot',
                'state' => 'Gujarat',
                'pincode' => '360001',
                'country' => 'INDIA',
            ]
        );

        $paidAt = now()->subHours($hoursAgo);

        $order = DonationOrder::query()->updateOrCreate(
            ['provider_payment_id' => $paymentId],
            [
                'donor_id' => $donor->id,
                'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
                'source_channel' => DonationAttributionService::CHANNEL_RAZORPAY_QR,
                'provider_order_id' => 'qr-'.$paymentId,
                'donor_name' => $donorName,
                'donor_email' => $donorEmail,
                'donor_phone' => $donorPhone,
                'city' => 'Rajkot',
                'state' => 'Gujarat',
                'pincode' => '360001',
                'country' => 'INDIA',
                'donor_country_code' => 'IN',
                'currency' => 'INR',
                'total_amount' => $amount,
                'status' => DonationOrder::STATUS_PAID,
                'paid_at' => $paidAt,
                'consent_indian_citizen' => true,
                'utm_source' => 'demo',
                'utm_medium' => 'seed',
                'utm_campaign' => 'demo_razorpay_qr',
            ]
        );

        DonationItem::query()->updateOrCreate(
            [
                'donation_order_id' => $order->id,
                'cause_id' => $cause->id,
            ],
            [
                'cause_package_id' => $package?->id,
                'cause' => $cause->slug ?: $cause->title,
                'title' => $package?->title ?: $qr->name,
                'quantity' => 1,
                'unit_amount' => $amount,
                'amount' => $amount,
                'meta' => [
                    'cause_title' => $cause->title,
                    'cause_slug' => $cause->slug,
                    'razorpay_qr_code_id' => $qr->razorpay_qr_code_id,
                    'qr_uuid' => $qr->qr_uuid,
                ],
            ]
        );

        PaymentEvent::query()->updateOrCreate(
            [
                'donation_order_id' => $order->id,
                'provider_payment_id' => $paymentId,
                'event' => 'payment.captured',
            ],
            [
                'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
                'amount' => $amount,
                'payload' => [
                    'payment' => [
                        'id' => $paymentId,
                        'amount' => (int) round($amount * 100),
                        'currency' => 'INR',
                        'status' => 'captured',
                        'method' => 'upi',
                        'qr_code_id' => $qr->razorpay_qr_code_id,
                    ],
                    'qr_code_id' => $qr->razorpay_qr_code_id,
                ],
                'created_at' => $paidAt,
            ]
        );

        return [
            'qr_name' => $qr->name,
            'donor_name' => $donorName,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'order_uuid' => $order->order_uuid,
        ];
    }
}
