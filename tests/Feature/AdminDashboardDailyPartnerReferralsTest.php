<?php

use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('includes daily partner referral figures on the dashboard', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $riya = User::factory()->create([
        'name' => 'Riya Patel',
        'referral_code' => 'rp',
    ]);
    User::factory()->create([
        'name' => 'Amit Shah',
        'referral_code' => 'as',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_daily_rp_1',
        'donor_name' => 'Daily Donor',
        'donor_email' => 'daily@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 2500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'rp',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_daily_rp_2',
        'donor_name' => 'Second Donor',
        'donor_email' => 'second@example.com',
        'donor_phone' => '9898237949',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'rp',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_daily_meta_ac',
        'donor_name' => 'Meta Donor',
        'donor_email' => 'meta@example.com',
        'donor_phone' => '9898237950',
        'currency' => 'INR',
        'total_amount' => 10,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_source' => 'meta',
        'utm_campaign' => '12/08 | Sadbhavna | Shravan | Ashvini',
        'utm_content' => '12/08 | Sadbhavna | Shravan | Ashvini | Old age 1000',
    ]);

    $ashvini = User::factory()->create([
        'name' => 'Ashvini',
        'referral_code' => 'ac',
    ]);

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('dailyPartnerReferrals')
            ->where('dailyPartnerReferrals.total_orders', 3)
            ->where('dailyPartnerReferrals.total_revenue', 3010)
            ->where('dailyPartnerReferrals.active_partners', 2)
            ->where('dailyPartnerReferrals.partners.0.name', 'Riya Patel')
            ->where('dailyPartnerReferrals.partners.0.paid_orders', 2)
            ->where('dailyPartnerReferrals.partners.0.revenue', 3000)
            ->where('dailyPartnerReferrals.partners.1.name', 'Ashvini')
            ->where('dailyPartnerReferrals.partners.1.paid_orders', 1)
            ->where('dailyPartnerReferrals.partners.1.revenue', 10)
            ->where('dailyPartnerReferrals.partners.2.name', 'Amit Shah')
            ->where('dailyPartnerReferrals.partners.2.paid_orders', 0)
            ->where('dailyPartnerReferrals.partners.2.revenue', 0));
});
