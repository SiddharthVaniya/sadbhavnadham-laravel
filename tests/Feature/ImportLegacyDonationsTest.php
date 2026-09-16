<?php

use App\Models\Cause;
use App\Models\DonationOrder;
use App\Services\LegacyDonationImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function legacySqlFixturePath(): string
{
    $path = storage_path('app/testing/legacy-donations-fixture.sql');
    File::ensureDirectoryExists(dirname($path));

    File::put($path, <<<'SQL'
CREATE TABLE `donations` (
  `id` bigint unsigned NOT NULL
);
INSERT INTO `donations` (`id`, `payment_id`, `razorpay_order_id`, `receipt_no`, `donor_name`, `amount`, `email`, `contact`, `donate_for`, `pan_number`, `address`, `status`, `payment_link_id`, `payment_link_url`, `created_at`, `updated_at`, `transferred_amount`, `transfer_status`, `transferred_at`) VALUES
(3, 'pay_LEGACY_001', NULL, NULL, 'Test Donor', 500.00, 'donor@example.com', '+919876543210', 'Tree', 'N/A', NULL, 'captured', NULL, NULL, '2025-05-13 13:52:38', '2025-05-13 13:52:38', NULL, 'pending', NULL),
(4, 'pay_LEGACY_002', 'order_LEGACY_002', NULL, 'Failed Donor', 100.00, 'failed@example.com', '+919111111111', 'balad', 'ABCDE1234F', NULL, 'failed_link_sent', NULL, NULL, '2025-05-14 10:00:00', '2025-05-14 10:05:00', NULL, 'failed', NULL),
(5, 'pay_LEGACY_003', NULL, NULL, 'Old Age Donor', 1500.00, 'old@example.com', '9876543210', 'Vruddhashram', 'N/A', NULL, 'captured', NULL, NULL, '2025-05-15 08:00:00', '2025-05-15 08:00:00', NULL, 'success', NULL);
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL
);
SQL);

    return $path;
}

it('dry-runs legacy import without writing rows', function () {
    Cause::factory()->create(['slug' => 'tree-plantation', 'title' => 'Tree Plantation']);
    Cause::factory()->create(['slug' => 'bull-shelter', 'title' => 'Bull Shelter']);
    Cause::factory()->create(['slug' => 'old-age-home', 'title' => 'Old Age Home']);
    Cause::factory()->create(['slug' => 'dog-shelter', 'title' => 'Dog Shelter']);
    Cause::factory()->create(['slug' => 'animal-hospital', 'title' => 'Animal Hospital']);

    $path = legacySqlFixturePath();

    $exit = Artisan::call('donations:import-legacy', ['path' => $path]);
    $output = Artisan::output();

    expect($exit)->toBe(0);
    expect(DonationOrder::query()->count())->toBe(0);
    expect($output)->toContain('DRY RUN');
    expect($output)->toContain('Rows parsed');
});

it('executes legacy import for captured and failed donations', function () {
    Cause::factory()->create(['slug' => 'tree-plantation', 'title' => 'Tree Plantation']);
    Cause::factory()->create(['slug' => 'bull-shelter', 'title' => 'Bull Shelter']);
    Cause::factory()->create(['slug' => 'old-age-home', 'title' => 'Old Age Home']);
    Cause::factory()->create(['slug' => 'dog-shelter', 'title' => 'Dog Shelter']);
    Cause::factory()->create(['slug' => 'animal-hospital', 'title' => 'Animal Hospital']);

    $path = legacySqlFixturePath();

    Artisan::call('donations:import-legacy', [
        'path' => $path,
        '--execute' => true,
    ]);

    expect(DonationOrder::query()->count())->toBe(3);

    $paid = DonationOrder::query()->where('provider_payment_id', 'pay_LEGACY_001')->first();
    expect($paid)->not->toBeNull();
    expect($paid->status)->toBe(DonationOrder::STATUS_PAID);
    expect((float) $paid->total_amount)->toBe(500.0);
    expect($paid->donor_phone)->toBe('9876543210');
    expect($paid->sheet_logged_at)->not->toBeNull();
    expect($paid->items()->first()?->cause_id)->not->toBeNull();
    expect($paid->address)->toBeNull();

    $failed = DonationOrder::query()->where('provider_payment_id', 'pay_LEGACY_002')->first();
    expect($failed->status)->toBe(DonationOrder::STATUS_FAILED);
    expect($failed->failed_at)->not->toBeNull();
    expect($failed->items()->first()?->cause)->toBe('bull-shelter');

    // Idempotent second run
    Artisan::call('donations:import-legacy', [
        'path' => $path,
        '--execute' => true,
    ]);

    expect(DonationOrder::query()->count())->toBe(3);
});

it('imports and backfills legacy addresses', function () {
    Cause::factory()->create(['slug' => 'tree-plantation', 'title' => 'Tree Plantation']);

    $path = storage_path('app/testing/legacy-address-fixture.sql');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, <<<'SQL'
INSERT INTO `donations` (`id`, `payment_id`, `razorpay_order_id`, `receipt_no`, `donor_name`, `amount`, `email`, `contact`, `donate_for`, `pan_number`, `address`, `status`, `payment_link_id`, `payment_link_url`, `created_at`, `updated_at`, `transferred_amount`, `transfer_status`, `transferred_at`) VALUES
(2001, 'pay_ADDR_001', NULL, NULL, 'Address Donor', 700.00, 'address@example.com', '+919888877776', 'Tree', 'N/A', 'A-502, Casa Vyoma, Ahmedabad', 'captured', NULL, NULL, '2025-06-01 10:00:00', '2025-06-01 10:00:00', NULL, 'pending', NULL);
SQL);

    Artisan::call('donations:import-legacy', [
        'path' => $path,
        '--execute' => true,
    ]);

    $order = DonationOrder::query()->where('provider_payment_id', 'pay_ADDR_001')->first();
    expect($order)->not->toBeNull();
    expect($order->address)->toBe('A-502, Casa Vyoma, Ahmedabad');

    $order->update(['address' => null]);

    Artisan::call('donations:import-legacy', [
        'path' => $path,
        '--execute' => true,
    ]);

    expect($order->fresh()->address)->toBe('A-502, Casa Vyoma, Ahmedabad');
});

it('maps legacy donate_for and statuses', function () {
    $service = app(LegacyDonationImportService::class);

    expect($service->mapCauseSlug('balad'))->toBe('bull-shelter');
    expect($service->mapCauseSlug('badad'))->toBe('bull-shelter');
    expect($service->mapCauseSlug('swan'))->toBe('dog-shelter');
    expect($service->mapCauseSlug('Vruddhashram'))->toBe('old-age-home');
    expect($service->mapStatus('captured'))->toBe(DonationOrder::STATUS_PAID);
    expect($service->mapStatus('failed_link_created'))->toBe(DonationOrder::STATUS_FAILED);
});
