<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use Database\Seeders\DemoDonationShowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

function createDonationShowAdmin(): User
{
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    Permission::firstOrCreate(['name' => 'manage receipts']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['view donations', 'view all donations', 'manage receipts']);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('seeds demo donation show scenarios for local ui testing', function () {
    seed(DemoDonationShowSeeder::class);

    expect(DonationOrder::query()->where('provider_payment_id', 'like', 'pay_DEMO_%')->count())->toBe(4)
        ->and(DonationOrder::query()->where('provider_payment_id', 'pay_DEMO_PAID_MIXED')->value('status'))
        ->toBe(DonationOrder::STATUS_PAID)
        ->and(DonationOrder::query()->where('provider_payment_id', 'pay_DEMO_FAILED_LINK')->value('status'))
        ->toBe(DonationOrder::STATUS_FAILED)
        ->and(DonationOrder::query()->where('provider_payment_id', 'pay_DEMO_FAILED_LINK')->value('payment_link_url'))
        ->not->toBeNull();
});

it('exposes mixed delivery statuses for paid donation show page', function () {
    $user = createDonationShowAdmin();
    $cause = Cause::factory()->create(['title' => 'Old Age Home']);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ui-mixed-order',
        'provider_payment_id' => 'pay_ui_mixed',
        'donor_name' => 'UI Mixed Donor',
        'donor_email' => 'ui.mixed@example.com',
        'donor_phone' => '9918710790',
        'currency' => 'INR',
        'total_amount' => 50,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => 1001,
        'receipt_sent_at' => now(),
        'whatsapp_sent_at' => null,
        'certificate_whatsapp_sent_at' => null,
        'receipt_whatsapp_sent_at' => now(),
        'sheet_logged_at' => now(),
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->title,
        'title' => 'Custom Donation',
        'quantity' => 1,
        'unit_amount' => 50,
        'amount' => 50,
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.is_paid', true)
            ->where('donation.delivery.email.status', 'sent')
            ->where('donation.delivery.whatsapp.status', 'not_sent')
            ->where('donation.delivery.certificate_whatsapp.status', 'not_sent')
            ->where('donation.delivery.receipt_whatsapp.status', 'sent')
            ->where('donation.delivery.sheet.status', 'logged')
            ->where('donation.can_resend_receipt_email', true)
            ->where('donation.can_resend_thank_you_whatsapp', true)
            ->where('donation.can_resend_certificate_whatsapp', true)
            ->where('donation.can_resend_receipt_whatsapp', true)
            ->where('donation.can_resend_sheet', true));
});

it('exposes payment link delivery for failed donation show page', function () {
    $user = createDonationShowAdmin();
    $cause = Cause::factory()->create(['title' => 'Old Age Home']);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'ui-failed-order',
        'provider_payment_id' => 'pay_ui_failed',
        'donor_name' => 'UI Failed Donor',
        'donor_email' => 'ui.failed@example.com',
        'donor_phone' => '9988776655',
        'currency' => 'INR',
        'total_amount' => 2100,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now(),
        'payment_link_id' => 'plink_ui_failed',
        'payment_link_url' => 'https://rzp.io/i/ui-failed',
        'payment_link_sent_at' => now(),
        'failed_sheet_logged_at' => now(),
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->title,
        'title' => 'Custom Donation',
        'quantity' => 1,
        'unit_amount' => 2100,
        'amount' => 2100,
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.is_failed', true)
            ->where('donation.delivery.payment_link_whatsapp.status', 'sent')
            ->where('donation.delivery.payment_link_whatsapp.url', 'https://rzp.io/i/ui-failed')
            ->where('donation.delivery.follow_up_sheet.status', 'logged')
            ->where('donation.can_notify_payment_link_email', true)
            ->where('donation.can_notify_payment_link_sms', true));
});
