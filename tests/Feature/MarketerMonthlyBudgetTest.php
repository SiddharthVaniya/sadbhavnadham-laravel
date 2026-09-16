<?php

use App\Models\DonationOrder;
use App\Models\MarketerMonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createBudgetAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage users']);
    Permission::firstOrCreate(['name' => 'view analytics']);
    Permission::firstOrCreate(['name' => 'view staff referrals']);

    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->syncPermissions(['manage users', 'view analytics', 'view staff referrals']);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

it('upserts this month target and spend when updating a user', function () {
    Permission::firstOrCreate(['name' => 'manage users']);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->syncPermissions(['manage users']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'referral_code' => 'mk',
        'donation_target' => 10000,
    ]);
    $target->assignRole('admin');

    actingAs($admin)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'referral_code' => 'mk',
            'donation_target' => 10000,
            'monthly_target_amount' => 50000,
            'monthly_spend_amount' => 12500.5,
            'roles' => ['admin'],
            'permissions' => [],
        ])
        ->assertRedirect(route('admin.users.index'));

    $budget = MarketerMonthlyBudget::query()
        ->where('user_id', $target->id)
        ->where('year_month', now()->format('Y-m'))
        ->first();

    expect($budget)->not->toBeNull()
        ->and($budget->target_amount)->toBe(50000)
        ->and((float) $budget->spend_amount)->toBe(12500.5);
});

it('includes monthly partner referrals with target and spend on the dashboard', function () {
    $admin = createBudgetAdmin();

    $riya = User::factory()->create([
        'name' => 'Riya Patel',
        'referral_code' => 'rp',
    ]);

    MarketerMonthlyBudget::query()->create([
        'user_id' => $riya->id,
        'year_month' => now()->format('Y-m'),
        'target_amount' => 10000,
        'spend_amount' => 2500,
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_month_rp_1',
        'donor_name' => 'Month Donor',
        'donor_email' => 'month@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 3000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->startOfMonth()->addDays(2),
        'utm_content' => 'rp',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_month_old',
        'donor_name' => 'Old Donor',
        'donor_email' => 'old@example.com',
        'donor_phone' => '9898237949',
        'currency' => 'INR',
        'total_amount' => 9000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subMonth()->startOfMonth()->addDays(2),
        'utm_content' => 'rp',
    ]);

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('monthlyPartnerReferrals')
            ->where('monthlyPartnerReferrals.total_orders', 1)
            ->where('monthlyPartnerReferrals.total_revenue', 3000)
            ->where('monthlyPartnerReferrals.total_target', 10000)
            ->where('monthlyPartnerReferrals.total_spend', 2500)
            ->where('monthlyPartnerReferrals.partners.0.name', 'Riya Patel')
            ->where('monthlyPartnerReferrals.partners.0.paid_orders', 1)
            ->where('monthlyPartnerReferrals.partners.0.revenue', 3000)
            ->where('monthlyPartnerReferrals.partners.0.target_amount', 10000)
            ->where('monthlyPartnerReferrals.partners.0.spend_amount', 2500));
});

it('shows this month target and spend on the marketer dashboard', function () {
    Role::firstOrCreate(['name' => 'digital_marketer', 'guard_name' => 'web']);

    $marketer = User::factory()->create([
        'referral_code' => 'ashvini',
        'donation_target' => 20000,
        'email' => 'ashvini-budget@example.com',
    ]);
    $marketer->assignRole('digital_marketer');

    MarketerMonthlyBudget::query()->create([
        'user_id' => $marketer->id,
        'year_month' => now()->format('Y-m'),
        'target_amount' => 8000,
        'spend_amount' => 1500,
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_marketer_month_1',
        'donor_name' => 'Marketer Donor',
        'donor_email' => 'md@example.com',
        'donor_phone' => '9898237999',
        'currency' => 'INR',
        'total_amount' => 2000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $marketer->id,
        'partner_code' => 'ashvini',
    ]);

    actingAs($marketer)
        ->get(route('marketer.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketer/Dashboard')
            ->where('monthlyBudget.year_month', now()->format('Y-m'))
            ->where('monthlyBudget.target_amount', 8000)
            ->where('monthlyBudget.spend_amount', 1500)
            ->where('target.goal', 8000)
            ->where('target.period', 'this_month')
            ->where('target.achieved', 2000));
});
