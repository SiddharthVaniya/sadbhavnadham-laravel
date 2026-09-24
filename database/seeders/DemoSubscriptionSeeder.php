<?php

namespace Database\Seeders;

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\Donor;
use App\Support\SubscriptionFrequency;
use Illuminate\Database\Seeder;

class DemoSubscriptionSeeder extends Seeder
{
    /**
     * Local-only fake subscriptions + recurring charges for admin UI testing.
     *
     * Covers: active, pending, halted, cancelled (with charged donation rows).
     *
     * Run: php artisan db:seed --class=DemoSubscriptionSeeder
     */
    public function run(): void
    {
        $cause = Cause::query()->where('is_active', true)->where('allow_recurring', true)->first()
            ?? Cause::query()->where('is_active', true)->first()
            ?? Cause::factory()->create([
                'title' => 'Old Age Home',
                'slug' => 'old-age-home-demo-sub',
                'is_active' => true,
                'allow_recurring' => true,
            ]);

        if (! $cause->allow_recurring) {
            $cause->update(['allow_recurring' => true]);
        }

        $scenarios = [
            $this->seedScenario(
                razorpaySubscriptionId: 'sub_DEMO_ACTIVE_001',
                paymentId: 'pay_DEMO_SUB_ACTIVE_C1',
                status: DonationSubscription::STATUS_ACTIVE,
                donorName: 'Demo Active Subscriber',
                donorEmail: 'demo.sub.active@example.com',
                donorPhone: '9811111101',
                amount: 501,
                cycle: 1,
                cause: $cause,
                extra: [
                    'started_at' => now()->subMonths(2),
                    'next_charge_at' => now()->addDays(12),
                    'billing_cycle_count' => 1,
                ],
            ),
            $this->seedScenario(
                razorpaySubscriptionId: 'sub_DEMO_PENDING_001',
                paymentId: 'pay_DEMO_SUB_PENDING_C1',
                status: DonationSubscription::STATUS_PENDING,
                donorName: 'Demo Pending Subscriber',
                donorEmail: 'demo.sub.pending@example.com',
                donorPhone: '9811111102',
                amount: 1100,
                cycle: 2,
                cause: $cause,
                extra: [
                    'started_at' => now()->subMonths(1),
                    'next_charge_at' => now()->addDays(3),
                    'billing_cycle_count' => 2,
                ],
            ),
            $this->seedScenario(
                razorpaySubscriptionId: 'sub_DEMO_HALTED_001',
                paymentId: 'pay_DEMO_SUB_HALTED_C2',
                status: DonationSubscription::STATUS_HALTED,
                donorName: 'Demo Halted Subscriber',
                donorEmail: 'demo.sub.halted@example.com',
                donorPhone: '9811111103',
                amount: 2100,
                cycle: 2,
                cause: $cause,
                extra: [
                    'started_at' => now()->subMonths(4),
                    'next_charge_at' => null,
                    'billing_cycle_count' => 2,
                ],
            ),
            $this->seedScenario(
                razorpaySubscriptionId: 'sub_DEMO_CANCELLED_001',
                paymentId: 'pay_DEMO_SUB_CANCELLED_C3',
                status: DonationSubscription::STATUS_CANCELLED,
                donorName: 'Demo Cancelled Subscriber',
                donorEmail: 'demo.sub.cancelled@example.com',
                donorPhone: '9811111104',
                amount: 3100,
                cycle: 3,
                cause: $cause,
                extra: [
                    'started_at' => now()->subMonths(6),
                    'next_charge_at' => null,
                    'ended_at' => now()->subDays(5),
                    'cancelled_at' => now()->subDays(5),
                    'cancel_reason' => 'Cancelled for local demo testing',
                    'billing_cycle_count' => 3,
                ],
            ),
        ];

        $this->command?->info('Demo subscription records ready:');
        $this->command?->table(
            ['Status', 'Subscriber', 'Payment ID', 'Admin donation', 'Admin subscription'],
            collect($scenarios)->map(fn (array $row) => [
                $row['status'],
                $row['donor_name'],
                $row['payment_id'],
                '/admin/donations/'.$row['order_uuid'],
                '/admin/subscriptions/'.$row['subscription_uuid'],
            ])->all()
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{status: string, donor_name: string, payment_id: string, order_uuid: string, subscription_uuid: string}
     */
    private function seedScenario(
        string $razorpaySubscriptionId,
        string $paymentId,
        string $status,
        string $donorName,
        string $donorEmail,
        string $donorPhone,
        float $amount,
        int $cycle,
        Cause $cause,
        array $extra = [],
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

        $subscription = DonationSubscription::query()->updateOrCreate(
            ['razorpay_subscription_id' => $razorpaySubscriptionId],
            array_merge([
                'donor_id' => $donor->id,
                'cause_id' => $cause->id,
                'frequency' => SubscriptionFrequency::MONTHLY,
                'quantity' => 1,
                'unit_amount' => $amount,
                'total_amount' => $amount,
                'currency' => 'INR',
                'item_title' => 'Demo Recurring Donation',
                'razorpay_plan_id' => 'plan_DEMO_'.strtoupper($status),
                'status' => $status,
                'donor_name' => $donorName,
                'donor_email' => $donorEmail,
                'donor_phone' => $donorPhone,
                'city' => 'Rajkot',
                'state' => 'Gujarat',
                'pincode' => '360001',
                'country' => 'INDIA',
                'donor_country_code' => 'IN',
                'consent_indian_citizen' => true,
                'consent_recurring' => true,
                'source_channel' => 'web',
                'utm_source' => 'demo',
                'utm_medium' => 'seed',
                'utm_campaign' => 'demo_subscription_'.$status,
            ], $extra)
        );

        $order = DonationOrder::query()->updateOrCreate(
            ['provider_payment_id' => $paymentId],
            [
                'donor_id' => $donor->id,
                'donation_subscription_id' => $subscription->id,
                'billing_cycle_number' => $cycle,
                'is_recurring' => true,
                'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
                'provider_order_id' => 'order_'.$paymentId,
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
                'paid_at' => now()->subHours(max(1, $cycle)),
                'consent_indian_citizen' => true,
                'source_channel' => 'web',
                'utm_source' => 'demo',
                'utm_medium' => 'seed',
                'utm_campaign' => 'demo_subscription_'.$status,
            ]
        );

        DonationItem::query()->updateOrCreate(
            [
                'donation_order_id' => $order->id,
                'cause_id' => $cause->id,
            ],
            [
                'cause' => $cause->title,
                'title' => 'Demo Recurring Donation',
                'quantity' => 1,
                'unit_amount' => $amount,
                'amount' => $amount,
            ]
        );

        return [
            'status' => $status,
            'donor_name' => $donorName,
            'payment_id' => $paymentId,
            'order_uuid' => $order->order_uuid,
            'subscription_uuid' => $subscription->subscription_uuid,
        ];
    }
}
