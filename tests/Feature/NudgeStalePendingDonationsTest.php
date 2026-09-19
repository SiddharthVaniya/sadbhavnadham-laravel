<?php

use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\SendPaymentLinkWhatsAppJob;
use App\Models\DonationOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function createStalePendingOrder(array $overrides = []): DonationOrder
{
    $createdAt = $overrides['created_at'] ?? now()->subMinutes(15);
    unset($overrides['created_at'], $overrides['updated_at']);

    $order = DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_stale_'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Stale Pending Donor',
        'donor_email' => 'stale-pending@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 501,
        'status' => DonationOrder::STATUS_PENDING,
    ], $overrides));

    $order->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->save();

    return $order->fresh();
}

it('marks pending checkouts older than five minutes as failed and queues payment link creation', function () {
    Bus::fake();

    $order = createStalePendingOrder([
        'created_at' => now()->subMinutes(6),
    ]);

    $this->artisan('donations:nudge-stale-pending')
        ->assertSuccessful();

    $order->refresh();

    expect($order->isFailed())->toBeTrue()
        ->and($order->failed_at)->not->toBeNull();

    Bus::assertDispatched(CreatePaymentLinkJob::class, function (CreatePaymentLinkJob $job): bool {
        return (fn () => $this->immediate)->call($job) === true;
    });
    Bus::assertNotDispatched(SendPaymentLinkWhatsAppJob::class);
});

it('processes newest stale pending orders first', function () {
    Bus::fake();

    $older = createStalePendingOrder([
        'created_at' => now()->subHours(5),
        'donor_name' => 'Older Pending',
    ]);
    $newer = createStalePendingOrder([
        'created_at' => now()->subMinutes(7),
        'donor_name' => 'Newer Pending',
    ]);

    $this->artisan('donations:nudge-stale-pending', ['--limit' => 1])
        ->assertSuccessful();

    expect($newer->refresh()->isFailed())->toBeTrue()
        ->and($older->refresh()->isPending())->toBeTrue();
});

it('does not convert pending checkouts younger than five minutes', function () {
    Bus::fake();

    $order = createStalePendingOrder([
        'created_at' => now()->subMinutes(3),
    ]);

    $this->artisan('donations:nudge-stale-pending')
        ->assertSuccessful();

    expect($order->refresh()->isPending())->toBeTrue()
        ->and($order->failed_at)->toBeNull();

    Bus::assertNothingDispatched();
});

it('skips stale pending checkouts without a sendable phone', function () {
    Bus::fake();

    $order = createStalePendingOrder([
        'created_at' => now()->subMinutes(20),
        'donor_phone' => 'upi-abc123',
    ]);

    $this->artisan('donations:nudge-stale-pending')
        ->assertSuccessful();

    expect($order->refresh()->isPending())->toBeTrue();

    Bus::assertNothingDispatched();
});

it('queues fail whatsapp when a stale pending order already has a payment link', function () {
    Bus::fake();

    $order = createStalePendingOrder([
        'created_at' => now()->subMinutes(7),
        'payment_link_id' => 'plink_existing',
        'payment_link_url' => 'https://rzp.io/i/existing',
    ]);

    $this->artisan('donations:nudge-stale-pending')
        ->assertSuccessful();

    expect($order->refresh()->isFailed())->toBeTrue();

    Bus::assertDispatched(SendPaymentLinkWhatsAppJob::class);
    Bus::assertNotDispatched(CreatePaymentLinkJob::class);
});

it('ignores pending checkouts older than the max age window', function () {
    Bus::fake();

    $order = createStalePendingOrder([
        'created_at' => now()->subDays(30),
    ]);

    $this->artisan('donations:nudge-stale-pending')
        ->assertSuccessful();

    expect($order->refresh()->isPending())->toBeTrue();

    Bus::assertNothingDispatched();
});

it('dry-run lists matches without changing status or queueing jobs', function () {
    Bus::fake();

    $order = createStalePendingOrder([
        'created_at' => now()->subMinutes(30),
    ]);

    $this->artisan('donations:nudge-stale-pending', ['--dry-run' => true])
        ->assertSuccessful();

    expect($order->refresh()->isPending())->toBeTrue();

    Bus::assertNothingDispatched();
});
