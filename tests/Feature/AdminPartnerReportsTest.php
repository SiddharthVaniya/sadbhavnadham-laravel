<?php

use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function partnerReportsUser(array $permissions, ?string $referralCode = null): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    $role = Role::findOrCreate('partner-reports-'.md5(implode(',', $permissions).($referralCode ?? '')));
    $role->syncPermissions($permissions);

    $user = User::factory()->create([
        'referral_code' => $referralCode,
    ]);
    $user->assignRole($role);

    return $user->fresh();
}

it('shows partner reports page with performance table filters', function () {
    $admin = partnerReportsUser([
        'view staff referrals',
        'view all donations',
    ], 'admin-sid');

    $partner = User::factory()->create([
        'name' => 'Ashwini Partner',
        'referral_code' => 'xvjsrg',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'partner-report-1',
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $partner->id,
        'partner_code' => 'xvjsrg',
        'state' => 'Gujarat',
    ]);

    actingAs($admin)
        ->get(route('admin.partner-reports.index', [
            'duration' => 'today',
            'state' => 'Gujarat',
            'partner_user_id' => $partner->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/PartnerReports/Index')
            ->where('filters.state', 'Gujarat')
            ->where('filters.partner_user_id', $partner->id)
            ->has('partners')
            ->has('filter_options.states')
            ->has('filter_options.partners')
            ->has('durationOptions'));
});

it('forbids partner reports without referral permission', function () {
    Permission::findOrCreate('view donations');
    $user = partnerReportsUser(['view donations']);

    actingAs($user)
        ->get(route('admin.partner-reports.index'))
        ->assertForbidden();
});
