<?php

use App\Models\DonationOrder;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\DonationPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('stores the razorpay failure and shows a short label on the donations list', function () {
    Bus::fake();

    foreach (['view donations', 'view all donations'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_failure_label',
        'donor_name' => 'Card Donor',
        'donor_email' => 'card@example.com',
        'donor_phone' => '9876504444',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    app(DonationPaymentService::class)->handleFailed([
        'id' => 'pay_failure_label',
        'order_id' => 'order_failure_label',
        'error_code' => 'BAD_REQUEST_ERROR',
        'error_description' => 'Payment failed because of invalid card details',
        'error_source' => 'customer',
        'error_step' => 'payment_authentication',
        'error_reason' => 'authentication_failed',
    ]);

    expect($order->refresh()->provider_payment_id)->toBe('pay_failure_label')
        ->and(PaymentEvent::query()->where('donation_order_id', $order->id)->where('event', 'payment.failed')->count())->toBe(1);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all', 'status' => 'failed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Index')
            ->where('donations.data', function ($rows) use ($order) {
                $row = collect($rows)->firstWhere('uuid', $order->order_uuid);

                return is_array($row)
                    && ($row['failure_label'] ?? null) === 'Customer · Invalid card'
                    && str_contains((string) ($row['failure_detail'] ?? ''), 'invalid card');
            }));

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.failure_label', 'Customer · Invalid card')
            ->where('donation.failure_detail', 'Payment failed because of invalid card details'));
});
