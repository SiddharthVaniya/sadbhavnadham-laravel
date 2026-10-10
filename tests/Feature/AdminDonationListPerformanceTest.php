<?php

use App\Models\DonationOrder;
use App\Models\User;
use App\Support\AdminDonationLaterPaid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function createListPerformanceAdmin(): User
{
    foreach (['manage donations', 'view all donations', 'view donations'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::firstOrCreate(['name' => 'donations_list_perf', 'guard_name' => 'web']);
    $role->syncPermissions(['manage donations', 'view all donations', 'view donations']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('warms later-paid matches for failed rows without one query per failed order', function () {
    AdminDonationLaterPaid::clearCache();

    $failed = collect(range(1, 5))->map(function (int $index) {
        return DonationOrder::create([
            'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
            'provider_order_id' => 'order_failed_perf_'.$index,
            'donor_name' => 'Failed Donor '.$index,
            'donor_email' => "failed{$index}@example.com",
            'donor_phone' => '980000000'.$index,
            'currency' => 'INR',
            'total_amount' => 100,
            'status' => DonationOrder::STATUS_FAILED,
            'failed_at' => now()->subHours(2),
        ]);
    });

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_paid_perf_1',
        'provider_payment_id' => 'pay_perf_1',
        'donor_name' => 'Failed Donor 1',
        'donor_email' => 'failed1@example.com',
        'donor_phone' => '9800000001',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subHour(),
    ]);

    DB::enableQueryLog();
    AdminDonationLaterPaid::warm($failed);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect(AdminDonationLaterPaid::summaryFor($failed[0]))->not->toBeNull()
        ->and(AdminDonationLaterPaid::summaryFor($failed[1]))->toBeNull()
        ->and(count($queries))->toBeLessThanOrEqual(2);
});

it('streams donation csv export without loading the full result set into memory via get', function () {
    $user = createListPerformanceAdmin();

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'export-chunk-1',
        'provider_payment_id' => 'pay_chunk_1',
        'donor_name' => 'Chunk Donor',
        'donor_email' => 'chunk@example.com',
        'donor_phone' => '9999999911',
        'currency' => 'INR',
        'total_amount' => 1200,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    actingAs($user);
    $response = get(route('admin.donations.export', [
        'duration' => 'all',
        'status' => DonationOrder::STATUS_PAID,
    ]));

    $response->assertOk();
    expect($response->streamedContent())->toContain('Chunk Donor');
});

it('does not call razorpay failure backfill on the donations index', function () {
    $user = createListPerformanceAdmin();

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_index_failed_1',
        'donor_name' => 'Index Failed',
        'donor_email' => 'index.failed@example.com',
        'donor_phone' => '9999999912',
        'currency' => 'INR',
        'total_amount' => 200,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all']))
        ->assertOk();
});
