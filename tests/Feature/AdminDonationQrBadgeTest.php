<?php

use App\Models\DonationOrder;
use App\Models\PaymentEvent;
use App\Models\RazorpayQrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    \App\Support\AdminInertiaData::clearQrLookupCache();
});

function createQrBadgeAdminUser(): User
{
    foreach (['view donations', 'view all donations', 'view qr codes'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations', 'view qr codes']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('marks razorpay qr donations with qr name on the admin donations index', function () {
    $user = createQrBadgeAdminUser();

    $qr = RazorpayQrCode::factory()->create([
        'name' => 'Temple Counter QR',
        'razorpay_qr_code_id' => 'qr_badge_counter',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'provider_order_id' => 'qr-pay_qr_badge_1',
        'provider_payment_id' => 'pay_qr_badge_1',
        'donor_name' => 'QR Badge Donor',
        'donor_email' => 'qr.badge@example.com',
        'donor_phone' => '9876500001',
        'currency' => 'INR',
        'total_amount' => 501,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    PaymentEvent::query()->create([
        'donation_order_id' => $order->id,
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'event' => 'payment.captured',
        'provider_payment_id' => 'pay_qr_badge_1',
        'amount' => 501,
        'payload' => ['qr_code_id' => $qr->razorpay_qr_code_id],
        'created_at' => now(),
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_payment_id' => 'pay_checkout_badge_1',
        'donor_name' => 'Checkout Donor',
        'donor_email' => 'checkout@example.com',
        'donor_phone' => '9876500002',
        'currency' => 'INR',
        'total_amount' => 250,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'is_recurring' => false,
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Index')
            ->where('donations.data', function ($rows) {
                $byPayment = collect($rows)->keyBy('payment_id');
                $qrRow = $byPayment->get('pay_qr_badge_1');
                $checkout = $byPayment->get('pay_checkout_badge_1');

                if (! is_array($qrRow) || ! is_array($checkout)) {
                    return false;
                }

                return ($qrRow['is_qr'] ?? false) === true
                    && ($qrRow['qr_code_name'] ?? null) === 'Temple Counter QR'
                    && filled($qrRow['qr_code_url'] ?? null)
                    && ($checkout['is_qr'] ?? true) === false
                    && ($checkout['qr_code_name'] ?? null) === null;
            }));
});

it('shows qr context on the admin donation detail page', function () {
    $user = createQrBadgeAdminUser();

    $qr = RazorpayQrCode::factory()->create([
        'name' => 'Website Footer QR',
        'razorpay_qr_code_id' => 'qr_badge_footer',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'provider_order_id' => 'qr-pay_qr_badge_detail',
        'provider_payment_id' => 'pay_qr_badge_detail',
        'donor_name' => 'QR Detail Donor',
        'donor_email' => 'qr.detail@example.com',
        'donor_phone' => '9876500003',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    PaymentEvent::query()->create([
        'donation_order_id' => $order->id,
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'event' => 'payment.captured',
        'provider_payment_id' => 'pay_qr_badge_detail',
        'amount' => 1100,
        'payload' => ['qr_code_id' => $qr->razorpay_qr_code_id],
        'created_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.is_qr', true)
            ->where('donation.qr_code_name', 'Website Footer QR')
            ->where('donation.qr_code_url', route('admin.qr-codes.show', $qr)));
});
