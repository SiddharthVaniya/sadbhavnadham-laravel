<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the admin donation detail inertia page', function () {
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    Permission::firstOrCreate(['name' => 'manage receipts']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view donations');
    $role->givePermissionTo('view all donations');
    $role->givePermissionTo('manage receipts');

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    $cause = Cause::factory()->create([
        'title' => 'Tree Plantation',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'detail-order',
        'provider_payment_id' => 'pay_detail_123',
        'donor_name' => 'Page Donor',
        'donor_email' => 'page@example.com',
        'donor_phone' => '9999999994',
        'date_of_birth' => now()->subYears(28)->toDateString(),
        'pan_number' => 'ABCDE1234F',
        'address' => '123 Donor Street',
        'pincode' => '360001',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'country' => 'India',
        'donor_country_code' => 'IN',
        'currency' => 'INR',
        'total_amount' => 5000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => 5,
        'receipt_sent_at' => now()->subHour(),
        'whatsapp_sent_at' => now()->subMinutes(30),
        'sheet_logged_at' => now()->subMinutes(20),
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->title,
        'title' => 'Tree Plantation',
        'quantity' => 1,
        'unit_amount' => 5000,
        'amount' => 5000,
        'meta' => [
            'honoree_names' => ['Asha Patel'],
        ],
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', [
            'donationOrder' => $order,
            'return' => '/admin/donations?duration=all&page=3&status=paid',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Show')
            ->where('back_url', '/admin/donations?duration=all&page=3&status=paid')
            ->where('donation.donor_name', 'Page Donor')
            ->where('donation.payment_id', 'pay_detail_123')
            ->where('donation.pan_number', 'ABCDE1234F')
            ->where('donation.receipt_number', 'MSCT-RZP-5')
            ->where('donation.receipt_label', 'Sent')
            ->where('donation.can_resend_receipt_email', true)
            ->where('donation.delivery.email.status', 'sent')
            ->where('donation.delivery.whatsapp.status', 'sent')
            ->where('donation.delivery.sheet.status', 'logged')
            ->has('donation.items', 1)
            ->where('donation.items.0.cause', 'Tree Plantation')
            ->where('donation.items.0.honoree_names', ['Tree 1: Asha Patel'])
            ->has('donation.receipt_resend_url'));
});
