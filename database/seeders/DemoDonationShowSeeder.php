<?php

namespace Database\Seeders;

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use Illuminate\Database\Seeder;

class DemoDonationShowSeeder extends Seeder
{
    /**
     * Local-only fake donations for testing Delivery & receipt UI states.
     *
     * Run: php artisan db:seed --class=DemoDonationShowSeeder
     */
    public function run(): void
    {
        $cause = Cause::query()->where('is_active', true)->first()
            ?? Cause::factory()->create([
                'title' => 'Old Age Home',
                'slug' => 'old-age-home-demo',
                'is_active' => true,
            ]);

        $paidMixed = $this->upsertOrder('pay_DEMO_PAID_MIXED', [
            'provider_order_id' => 'order_DEMO_PAID_MIXED',
            'donor_name' => 'Demo Paid Mixed',
            'donor_email' => 'demo.paid.mixed@example.com',
            'donor_phone' => '9918710790',
            'address' => 'Amb-Una',
            'city' => 'Una',
            'state' => 'Himachal Pradesh',
            'pincode' => '177203',
            'country' => 'INDIA',
            'total_amount' => 50,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => now()->subHour(),
            'receipt_number' => 8498,
            'receipt_sent_at' => now()->subHour(),
            'whatsapp_sent_at' => null,
            'certificate_whatsapp_sent_at' => null,
            'receipt_whatsapp_sent_at' => now()->subHour(),
            'sheet_logged_at' => now()->subHour(),
            'utm_source' => 'meta',
            'utm_medium' => 'Instagram_Reels',
            'utm_campaign' => 'demo_paid_mixed',
            'attr_source' => 'meta',
            'attr_platform' => 'instagram',
            'device_type' => 'mobile',
            'ip_address' => '103.25.10.12',
            'ip_city' => 'Una',
            'ip_region_name' => 'Himachal Pradesh',
            'ip_country_code' => 'IN',
            'ip_country_name' => 'India',
            'landing_path' => '/donate',
        ], $cause, 'Custom Donation');

        $paidPending = $this->upsertOrder('pay_DEMO_PAID_PENDING', [
            'provider_order_id' => 'order_DEMO_PAID_PENDING',
            'donor_name' => 'Demo Paid Pending Delivery',
            'donor_email' => 'demo.paid.pending@example.com',
            'donor_phone' => '9876543210',
            'address' => '12 Demo Street',
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'pincode' => '360001',
            'country' => 'India',
            'total_amount' => 1100,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => now()->subMinutes(20),
            'receipt_number' => 8499,
            'receipt_sent_at' => null,
            'whatsapp_sent_at' => null,
            'certificate_whatsapp_sent_at' => null,
            'receipt_whatsapp_sent_at' => null,
            'sheet_logged_at' => null,
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'demo_paid_pending',
            'device_type' => 'desktop',
        ], $cause, 'General Donation');

        $paidFailedEmail = $this->upsertOrder('pay_DEMO_PAID_EMAIL_FAIL', [
            'provider_order_id' => 'order_DEMO_PAID_EMAIL_FAIL',
            'donor_name' => 'Demo Email Failed',
            'donor_email' => 'demo.email.failed@example.com',
            'donor_phone' => '9123456780',
            'total_amount' => 501,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => now()->subDays(1),
            'receipt_number' => 8500,
            'receipt_sent_at' => null,
            'receipt_failed_at' => now()->subDay(),
            'receipt_last_error' => 'SMTP rejected: mailbox unavailable (demo)',
            'whatsapp_sent_at' => now()->subDay(),
            'certificate_whatsapp_sent_at' => now()->subDay(),
            'receipt_whatsapp_sent_at' => null,
            'sheet_logged_at' => now()->subDay(),
        ], $cause, 'Seva Donation');

        $failedRecovery = $this->upsertOrder('pay_DEMO_FAILED_LINK', [
            'provider_order_id' => 'order_DEMO_FAILED_LINK',
            'donor_name' => 'Demo Failed Recovery',
            'donor_email' => 'demo.failed@example.com',
            'donor_phone' => '9988776655',
            'address' => 'Recovery Lane',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'pincode' => '380001',
            'country' => 'India',
            'total_amount' => 2100,
            'status' => DonationOrder::STATUS_FAILED,
            'failed_at' => now()->subMinutes(45),
            'paid_at' => null,
            'receipt_number' => null,
            'payment_link_id' => 'plink_DEMO_FAILED_001',
            'payment_link_url' => 'https://rzp.io/i/demo-failed-link',
            'payment_link_sent_at' => now()->subMinutes(40),
            'payment_link_email_sent_at' => null,
            'payment_link_sms_sent_at' => null,
            'failed_sheet_logged_at' => now()->subMinutes(44),
            'utm_source' => 'meta',
            'utm_medium' => 'instagram',
            'utm_campaign' => 'demo_failed_recovery',
            'device_type' => 'mobile',
        ], $cause, 'Custom Donation');

        $this->command?->info('Demo donation show records ready:');
        $this->command?->table(
            ['Scenario', 'Payment ID', 'Admin URL hint'],
            [
                ['Paid · mixed delivery', $paidMixed->provider_payment_id, '/admin/donations/'.$paidMixed->id],
                ['Paid · all not sent', $paidPending->provider_payment_id, '/admin/donations/'.$paidPending->id],
                ['Paid · email failed', $paidFailedEmail->provider_payment_id, '/admin/donations/'.$paidFailedEmail->id],
                ['Failed · payment link', $failedRecovery->provider_payment_id, '/admin/donations/'.$failedRecovery->id],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertOrder(string $paymentId, array $attributes, Cause $cause, string $itemTitle): DonationOrder
    {
        $order = DonationOrder::query()->updateOrCreate(
            ['provider_payment_id' => $paymentId],
            array_merge([
                'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
                'currency' => 'INR',
                'donor_country_code' => 'IN',
                'consent_indian_citizen' => true,
                'source_channel' => 'web',
            ], $attributes)
        );

        DonationItem::query()->updateOrCreate(
            [
                'donation_order_id' => $order->id,
                'cause_id' => $cause->id,
            ],
            [
                'cause' => $cause->title,
                'title' => $itemTitle,
                'quantity' => 1,
                'unit_amount' => $order->total_amount,
                'amount' => $order->total_amount,
            ]
        );

        return $order;
    }
}
