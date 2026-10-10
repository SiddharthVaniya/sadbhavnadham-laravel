<?php

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use App\Support\Attribution\AttributionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function createDonationExportAdmin(): User
{
    foreach (['view donations', 'view all donations', 'export donations'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::firstOrCreate(['name' => 'donation_export_admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations', 'export donations']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @return list<string>
 */
function csvDataLines(string $csv): array
{
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
    $lines = preg_split("/\r\n|\n|\r/", trim($csv)) ?: [];

    array_shift($lines);

    return array_values(array_filter($lines, fn (string $line) => $line !== ''));
}

it('exports csv rows matching every applied list filter', function () {
    $user = createDonationExportAdmin();
    $partner = User::factory()->create([
        'name' => 'Export Partner',
        'referral_code' => 'EXPART',
    ]);

    $cause = Cause::factory()->create(['title' => 'Education']);
    $otherCause = Cause::factory()->create(['title' => 'Health']);
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'School Kit',
    ]);
    $otherPackage = CausePackage::factory()->create([
        'cause_id' => $otherCause->id,
        'title' => 'Medicine Pack',
    ]);

    $match = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'export-match-order',
        'provider_payment_id' => 'pay_export_match',
        'donor_name' => 'Match Filter Donor',
        'donor_email' => 'match.filter@example.com',
        'donor_phone' => '9111111101',
        'currency' => 'INR',
        'total_amount' => 750,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'attr_source' => AttributionTaxonomy::SOURCE_META,
        'attr_platform' => AttributionTaxonomy::PLATFORM_FACEBOOK,
        'utm_source' => 'facebook',
        'utm_campaign' => 'spring-drive',
        'utm_content' => 'telecaller-a',
        'partner_user_id' => $partner->id,
        'partner_code' => 'EXPART',
    ]);

    DonationItem::create([
        'donation_order_id' => $match->id,
        'cause_id' => $cause->id,
        'cause_package_id' => $package->id,
        'cause' => $cause->title,
        'title' => $package->title,
        'quantity' => 1,
        'unit_amount' => 750,
        'amount' => 750,
    ]);

    $miss = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_OFFLINE,
        'provider_order_id' => 'export-miss-order',
        'provider_payment_id' => 'pay_export_miss',
        'donor_name' => 'Miss Filter Donor',
        'donor_email' => 'miss.filter@example.com',
        'donor_phone' => '9111111102',
        'currency' => 'INR',
        'total_amount' => 900,
        'status' => DonationOrder::STATUS_FAILED,
        'attr_source' => AttributionTaxonomy::SOURCE_ORGANIC,
        'utm_campaign' => 'other-campaign',
        'utm_content' => 'telecaller-b',
    ]);

    DonationItem::create([
        'donation_order_id' => $miss->id,
        'cause_id' => $otherCause->id,
        'cause_package_id' => $otherPackage->id,
        'cause' => $otherCause->title,
        'title' => $otherPackage->title,
        'quantity' => 1,
        'unit_amount' => 900,
        'amount' => 900,
    ]);

    $filters = [
        'duration' => 'all',
        'status' => DonationOrder::STATUS_PAID,
        'provider' => DonationOrder::PROVIDER_RAZORPAY,
        'cause_id' => $cause->id,
        'package_id' => $package->id,
        'cause_title' => $package->title,
        'source' => AttributionTaxonomy::SOURCE_META,
        'platform' => AttributionTaxonomy::PLATFORM_FACEBOOK,
        'partner_user_id' => $partner->id,
        'utm_campaign' => 'spring-drive',
        'utm_content' => 'telecaller-a',
        'search' => 'Match Filter',
        'sort' => 'cause',
        'dir' => 'desc',
    ];

    actingAs($user);

    $index = get(route('admin.donations.index', $filters));
    $index->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Donations/Index')
        ->has('donations.data', 1)
        ->where('donations.data.0.id', $match->id));

    $response = get(route('admin.donations.export', $filters));
    $response->assertOk();

    $csv = $response->streamedContent();
    $rows = csvDataLines($csv);

    expect($rows)->toHaveCount(1)
        ->and($csv)->toContain('Match Filter Donor')
        ->and($csv)->toContain('pay_export_match')
        ->and($csv)->not->toContain('Miss Filter Donor')
        ->and($csv)->not->toContain('pay_export_miss');
});

it('exports every matching row even when list sort params are present and result exceeds chunk size', function () {
    $user = createDonationExportAdmin();

    foreach (range(1, 250) as $index) {
        $order = DonationOrder::create([
            'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
            'provider_order_id' => 'chunk-skip-'.$index,
            'provider_payment_id' => 'pay_chunk_skip_'.$index,
            'donor_name' => 'Chunk Skip Donor '.$index,
            'donor_email' => "chunk.skip{$index}@example.com",
            'donor_phone' => sprintf('9222%06d', $index),
            'currency' => 'INR',
            'total_amount' => 100 + $index,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => now()->subMinutes($index),
        ]);

        $order->forceFill([
            'created_at' => now()->subMinutes(250 - $index),
        ])->save();
    }

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'chunk-skip-failed',
        'provider_payment_id' => 'pay_chunk_skip_failed',
        'donor_name' => 'Chunk Skip Failed',
        'donor_email' => 'chunk.skip.failed@example.com',
        'donor_phone' => '9222999999',
        'currency' => 'INR',
        'total_amount' => 50,
        'status' => DonationOrder::STATUS_FAILED,
    ]);

    actingAs($user);
    $response = get(route('admin.donations.export', [
        'duration' => 'all',
        'status' => DonationOrder::STATUS_PAID,
        'sort' => 'created_at_ts',
        'dir' => 'desc',
    ]));

    $response->assertOk();
    $rows = csvDataLines($response->streamedContent());

    expect($rows)->toHaveCount(250)
        ->and($response->streamedContent())->not->toContain('Chunk Skip Failed');
});

it('exports all matching records beyond 5000 rows without truncating', function () {
    $user = createDonationExportAdmin();
    $now = now()->format('Y-m-d H:i:s');
    $batch = [];

    for ($i = 1; $i <= 5100; $i++) {
        $batch[] = [
            'order_uuid' => (string) Str::uuid(),
            'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
            'provider_order_id' => 'bulk-export-'.$i,
            'provider_payment_id' => 'pay_bulk_export_'.$i,
            'donor_name' => 'Bulk Export Donor '.$i,
            'donor_email' => "bulk.export{$i}@example.com",
            'donor_phone' => sprintf('9333%06d', $i),
            'currency' => 'INR',
            'total_amount' => 125,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (count($batch) === 250) {
            DB::table('donation_orders')->insert($batch);
            $batch = [];
        }
    }

    if ($batch !== []) {
        DB::table('donation_orders')->insert($batch);
    }

    DB::table('donation_orders')->insert([
        'order_uuid' => (string) Str::uuid(),
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'bulk-export-excluded',
        'provider_payment_id' => 'pay_bulk_export_excluded',
        'donor_name' => 'Bulk Export Excluded',
        'donor_email' => 'bulk.export.excluded@example.com',
        'donor_phone' => '9333999999',
        'currency' => 'INR',
        'total_amount' => 125,
        'status' => DonationOrder::STATUS_PENDING,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    actingAs($user);
    $response = get(route('admin.donations.export', [
        'duration' => 'all',
        'status' => DonationOrder::STATUS_PAID,
        'sort' => 'total_amount',
        'dir' => 'asc',
    ]));

    $response->assertOk();
    $csv = $response->streamedContent();
    $rows = csvDataLines($csv);

    expect($rows)->toHaveCount(5100)
        ->and($csv)->toContain('Bulk Export Donor 1')
        ->and($csv)->toContain('Bulk Export Donor 5100')
        ->and($csv)->not->toContain('Bulk Export Excluded');
});
