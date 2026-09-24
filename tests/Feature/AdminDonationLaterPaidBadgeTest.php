<?php

use App\Models\DonationOrder;
use App\Models\User;
use App\Support\AdminDonationLaterPaid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    AdminDonationLaterPaid::clearCache();
});

function createLaterPaidBadgeAdmin(): User
{
    foreach (['view donations', 'view all donations'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('marks failed donations that later paid on the admin index', function () {
    $user = createLaterPaidBadgeAdmin();

    $failed = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_later_paid_fail',
        'provider_payment_id' => null,
        'donor_name' => 'Prabodh',
        'donor_email' => 'prabodh@example.com',
        'donor_phone' => '9876501111',
        'currency' => 'INR',
        'total_amount' => 2100,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now()->subMinutes(5),
        'created_at' => now()->subMinutes(5),
    ]);

    $paid = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_later_paid_ok',
        'provider_payment_id' => 'pay_later_paid_ok',
        'donor_name' => 'Prabodh',
        'donor_email' => 'prabodh@example.com',
        'donor_phone' => '9876501111',
        'currency' => 'INR',
        'total_amount' => 2100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subMinutes(2),
        'created_at' => now()->subMinutes(2),
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_payment_id' => 'pay_other_fail',
        'donor_name' => 'Other Donor',
        'donor_email' => 'other@example.com',
        'donor_phone' => '9876502222',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now()->subMinutes(3),
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all', 'status' => 'failed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Index')
            ->where('donations.data', function ($rows) use ($failed, $paid) {
                $byUuid = collect($rows)->keyBy('uuid');
                $failedRow = $byUuid->get($failed->order_uuid);
                $other = collect($rows)->firstWhere('payment_id', 'pay_other_fail');

                if (! is_array($failedRow) || ! is_array($other)) {
                    return false;
                }

                return ($failedRow['later_paid'] ?? false) === true
                    && ($failedRow['later_paid_url'] ?? null) === route('admin.donations.show', $paid)
                    && ($failedRow['later_paid_payment_id'] ?? null) === 'pay_later_paid_ok'
                    && ($other['later_paid'] ?? true) === false;
            }));
});

it('shows later paid context on the failed donation detail page', function () {
    $user = createLaterPaidBadgeAdmin();

    $failed = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_detail_fail',
        'donor_name' => 'Retry Donor',
        'donor_email' => 'retry@example.com',
        'donor_phone' => '9876503333',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now()->subHour(),
    ]);

    $paid = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_payment_id' => 'pay_detail_ok',
        'donor_name' => 'Retry Donor',
        'donor_email' => 'retry@example.com',
        'donor_phone' => '9876503333',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subMinutes(30),
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $failed))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.later_paid', true)
            ->where('donation.later_paid_url', route('admin.donations.show', $paid))
            ->where('donation.later_paid_payment_id', 'pay_detail_ok'));
});

it('does not mark later paid outside the 24 hour window', function () {
    $user = createLaterPaidBadgeAdmin();

    $failed = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_old_fail',
        'donor_name' => 'Old Fail',
        'donor_email' => 'oldfail@example.com',
        'donor_phone' => '9876504444',
        'currency' => 'INR',
        'total_amount' => 501,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now()->subHours(30),
        'created_at' => now()->subHours(30),
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_payment_id' => 'pay_after_window',
        'donor_name' => 'Old Fail',
        'donor_email' => 'oldfail@example.com',
        'donor_phone' => '9876504444',
        'currency' => 'INR',
        'total_amount' => 501,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subHours(2),
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $failed))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.later_paid', false)
            ->where('donation.later_paid_url', null));
});
