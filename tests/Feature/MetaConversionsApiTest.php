<?php

use App\Jobs\SendMetaCapiPurchaseJob;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\MetaCapiEventLog;
use App\Models\MetaPixel;
use App\Models\User;
use App\Services\Meta\MetaConversionsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function capiAdmin(): \App\Models\User
{
    Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['edit users']);

    $admin = \App\Models\User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function capiTestConfig(): void
{
    config([
        'meta_capi.enabled' => true,
        'meta_capi.allow_database_pixels' => true,
        'meta_capi.pixels' => [
            [
                'label' => 'Env test pixel',
                'pixel_id' => '111222333',
                'access_token' => 'env-test-token',
                'is_active' => true,
                'send_purchase' => true,
                'send_initiate_checkout' => true,
                'test_event_code' => null,
            ],
        ],
    ]);
}

it('stores meta pixels without leaking tokens to inertia when database pixels allowed', function () {
    config(['meta_capi.allow_database_pixels' => true]);

    $admin = capiAdmin();

    actingAs($admin)
        ->post(route('admin.meta.pixels.store'), [
            'label' => 'Pixel A',
            'pixel_id' => '1436406881878584',
            'access_token' => 'secret-capi-token',
            'is_active' => true,
            'send_purchase' => true,
            'send_initiate_checkout' => true,
        ])
        ->assertRedirect(route('admin.meta.pixels'));

    actingAs($admin)
        ->get(route('admin.meta.pixels'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Meta/Pixels')
            ->has('envPixels')
            ->where('pixels.0.label', 'Pixel A')
            ->where('pixels.0.has_access_token', true)
            ->missing('pixels.0.access_token'));
});

it('sends purchase events to meta capi using env pixel credentials', function () {
    capiTestConfig();

    $order = DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_capi_test_1',
        'donor_name' => 'Test Donor',
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'total_amount' => 500,
        'currency' => 'INR',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '919876543210',
        'landing_path' => '/donate/tree?fbclid=test-click-id',
        'ip_address' => '203.0.113.10',
    ]);

    DonationItem::query()->create([
        'donation_order_id' => $order->id,
        'cause' => 'tree-plantation',
        'title' => 'Tree',
        'quantity' => 1,
        'unit_amount' => 500,
        'amount' => 500,
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
    ]);

    app(MetaConversionsApiService::class)->sendPurchase($order);

    Http::assertSentCount(1);

    expect(MetaCapiEventLog::query()->where('status', MetaCapiEventLog::STATUS_SUCCESS)->count())->toBe(1);

    $log = MetaCapiEventLog::query()->first();
    expect($log?->event_name)->toBe('Purchase')
        ->and($log?->donation_order_id)->toBe($order->id);

    $pixel = MetaPixel::query()->where('pixel_id', '111222333')->first();
    expect($pixel)->not->toBeNull()
        ->and($pixel->hasAccessToken())->toBeFalse();
});

it('filters capi event logs by pixel event and marketer sid', function () {
    $admin = capiAdmin();
    $marketer = User::factory()->create(['name' => 'Ash Test', 'referral_code' => 'ashcapi']);
    $otherMarketer = User::factory()->create(['name' => 'Other', 'referral_code' => 'othercapi']);

    $pixelA = MetaPixel::factory()->create(['label' => 'Pixel A', 'pixel_id' => '900000000000001']);
    $pixelB = MetaPixel::factory()->create(['label' => 'Pixel B', 'pixel_id' => '900000000000002']);

    $orderAsh = DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_filter_ash',
        'donor_name' => 'Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '919876543210',
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'total_amount' => 100,
        'currency' => 'INR',
        'partner_user_id' => $marketer->id,
        'partner_code' => 'ashcapi',
    ]);

    $orderOther = DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_filter_other',
        'donor_name' => 'Donor 2',
        'donor_email' => 'donor2@example.com',
        'donor_phone' => '919876543211',
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'total_amount' => 200,
        'currency' => 'INR',
        'partner_user_id' => $otherMarketer->id,
        'partner_code' => 'othercapi',
    ]);

    MetaCapiEventLog::query()->create([
        'meta_pixel_id' => $pixelA->id,
        'donation_order_id' => $orderAsh->id,
        'event_name' => 'Purchase',
        'event_id' => 'purchase_ash',
        'status' => MetaCapiEventLog::STATUS_SUCCESS,
        'sent_at' => now(),
    ]);

    MetaCapiEventLog::query()->create([
        'meta_pixel_id' => $pixelA->id,
        'donation_order_id' => $orderOther->id,
        'event_name' => 'InitiateCheckout',
        'event_id' => 'checkout_other',
        'status' => MetaCapiEventLog::STATUS_SUCCESS,
        'sent_at' => now(),
    ]);

    MetaCapiEventLog::query()->create([
        'meta_pixel_id' => $pixelB->id,
        'donation_order_id' => $orderAsh->id,
        'event_name' => 'Purchase',
        'event_id' => 'purchase_ash_b',
        'status' => MetaCapiEventLog::STATUS_ERROR,
        'sent_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.meta.pixels', [
            'meta_pixel_id' => $pixelA->id,
            'event_name' => 'Purchase',
            'partner_user_id' => $marketer->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Meta/Pixels')
            ->has('eventLogs.data', 1)
            ->where('eventLogs.data.0.event_name', 'Purchase')
            ->where('eventLogs.data.0.sid', 'ashcapi')
            ->where('eventLogs.data.0.partner_name', 'Ash Test'));
});

it('queues capi purchase job when an order is completed as paid', function () {
    Queue::fake();

    $order = DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_capi_job_test',
        'donor_name' => 'Job Test',
        'donor_email' => 'job@example.com',
        'donor_phone' => '9876543210',
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'total_amount' => 100,
        'currency' => 'INR',
    ]);

    app(\App\Services\DonationPaymentService::class)->completePaidOrder($order);

    Queue::assertPushed(SendMetaCapiPurchaseJob::class, fn (SendMetaCapiPurchaseJob $job) => $job->donationOrderId === $order->id);
});
