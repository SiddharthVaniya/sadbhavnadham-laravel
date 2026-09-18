<?php

use App\Jobs\LogFailedDonationFollowUpSheetJob;
use App\Jobs\RefreshFailedDonationFollowUpPaymentLinkJob;
use App\Models\DonationOrder;
use App\Models\User;
use App\Services\FailedDonationFollowUpSheetLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

function createFailedFollowUpOrder(array $overrides = []): DonationOrder
{
    return DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_followup_'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Follow Up Donor',
        'donor_email' => 'followup@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 2100,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now(),
    ], $overrides));
}

function createFollowUpViewer(): User
{
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    Permission::firstOrCreate(['name' => 'manage receipts']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations', 'manage receipts']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('skips follow-up sheet job when sheet id is not configured', function () {
    $order = createFailedFollowUpOrder();
    $logger = mock(FailedDonationFollowUpSheetLogger::class);
    $logger->shouldReceive('isConfigured')->once()->andReturn(false);
    $logger->shouldNotReceive('log');

    (new LogFailedDonationFollowUpSheetJob($order))->handle($logger);

    expect($order->fresh()->failed_sheet_logged_at)->toBeNull();
});

it('skips follow-up sheet job when already logged', function () {
    $order = createFailedFollowUpOrder([
        'failed_sheet_logged_at' => now()->subHour(),
    ]);

    $logger = mock(FailedDonationFollowUpSheetLogger::class);
    $logger->shouldReceive('isConfigured')->once()->andReturn(true);
    $logger->shouldNotReceive('log');

    (new LogFailedDonationFollowUpSheetJob($order))->handle($logger);
});

it('skips follow-up sheet job when order is not failed', function () {
    $order = createFailedFollowUpOrder([
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'failed_at' => null,
    ]);

    $logger = mock(FailedDonationFollowUpSheetLogger::class);
    $logger->shouldReceive('isConfigured')->once()->andReturn(true);
    $logger->shouldNotReceive('log');

    (new LogFailedDonationFollowUpSheetJob($order))->handle($logger);
});

it('logs follow-up sheet and stamps failed_sheet_logged_at', function () {
    $order = createFailedFollowUpOrder([
        'failed_sheet_logged_at' => null,
    ]);

    $logger = mock(FailedDonationFollowUpSheetLogger::class);
    $logger->shouldReceive('isConfigured')->once()->andReturn(true);
    $logger->shouldReceive('log')->once()->withArgs(fn ($donationOrder) => $donationOrder instanceof DonationOrder);

    (new LogFailedDonationFollowUpSheetJob($order))->handle($logger);

    expect($order->fresh()->failed_sheet_logged_at)->not->toBeNull();
});

it('dispatches follow-up sheet job when markAsFailed is called', function () {
    Bus::fake([LogFailedDonationFollowUpSheetJob::class]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_pending_fail_'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Pending Fail',
        'donor_email' => 'pending-fail@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $order->markAsFailed();

    Bus::assertDispatched(LogFailedDonationFollowUpSheetJob::class);
    expect($order->fresh()->isFailed())->toBeTrue();
});

it('refresh payment link job calls logger when configured', function () {
    $order = createFailedFollowUpOrder([
        'payment_link_url' => 'https://rzp.io/i/followup',
    ]);

    $logger = mock(FailedDonationFollowUpSheetLogger::class);
    $logger->shouldReceive('isConfigured')->once()->andReturn(true);
    $logger->shouldReceive('refreshPaymentLink')->once()->withArgs(
        fn (DonationOrder $donationOrder) => $donationOrder->id === $order->id
    );

    (new RefreshFailedDonationFollowUpPaymentLinkJob($order->id))->handle($logger);
});

it('dispatches follow-up sheet job from payment failure handler', function () {
    Bus::fake();

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_handle_fail_'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Handle Fail',
        'donor_email' => 'handle-fail@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 700,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    app(\App\Services\DonationPaymentService::class)->handleFailed([
        'id' => 'pay_handle_fail',
        'order_id' => $order->provider_order_id,
        'status' => 'failed',
        'amount' => 70000,
    ]);

    Bus::assertDispatched(LogFailedDonationFollowUpSheetJob::class);
});

it('exposes follow-up sheet delivery status for failed donations', function () {
    $user = createFollowUpViewer();
    $order = createFailedFollowUpOrder([
        'failed_sheet_logged_at' => now()->subMinutes(15),
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.delivery.follow_up_sheet.status', 'logged')
            ->where('donation.delivery.follow_up_sheet.label', 'Logged'));
});
