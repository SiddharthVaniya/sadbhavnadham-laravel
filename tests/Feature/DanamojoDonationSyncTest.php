<?php

use App\Models\Cause;
use App\Models\DonationOrder;
use App\Services\Danamojo\DanamojoDonationImporter;
use App\Services\DonationAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function sampleDanamojoDonation(array $overrides = []): array
{
    return array_replace_recursive([
        'donationDate' => '2026-07-24 22:56:56',
        'donationInfoId' => 382670,
        'email' => 'sanjay_morzaria@hotmail.co.uk',
        'fullName' => 'sanjay Morzaria ',
        'address' => '55 sefton avenue ',
        'country' => 'United Kingdom',
        'state' => 'England',
        'city' => 'Sefton Avenue',
        'pincode' => 'Ha3 5jp ',
        'nationality' => 'United Kingdom',
        'idProof' => 'Driving License',
        'id' => 'morza3t532145',
        'currency' => 'USD',
        'totalDonationAmt' => '3858.68',
        'totalDonationAmtLocal' => '39.96',
        'paymentOption' => 'Stripe',
        'paymentStatus' => 'Verified',
        'fcra' => true,
        'mobile' => '+44 7932623852',
        'refererUrl' => 'https://donate.sadbhavnadham.org/donate/danamojo-widget',
        'utm_campaign' => null,
        'device' => 'Mobile',
        'recurring' => false,
        'international' => true,
        'receiptLink' => 'https://danamojo.org/receipt/382670.pdf',
        'subId' => null,
        'donation_details' => [
            [
                'donationProductName' => 'Tree Plantation',
                'donationProductQty' => null,
                'receiptNumber' => 'DM-0000000007',
                'receiptSendDate' => '2026-07-24 22:58:40',
            ],
        ],
    ], $overrides);
}

it('imports a verified danamojo donation and is idempotent on resync', function () {
    config([
        'danamojo.api_key_secret' => 'test-secret',
        'danamojo.queue_sheet_on_import' => false,
        'danamojo.send_receipt_email_on_import' => false,
        'danamojo.send_whatsapp_on_import' => false,
    ]);

    $cause = Cause::factory()->create([
        'title' => 'Tree Plantation',
        'slug' => 'tree-plantation',
    ]);

    Http::fake([
        'api.danamojo.org/*' => Http::response([
            'status' => 1,
            'data' => [sampleDanamojoDonation()],
        ], 200),
    ]);

    $importer = app(DanamojoDonationImporter::class);

    $first = $importer->sync(now()->subDay(), now());
    expect($first)->toMatchArray([
        'fetched' => 1,
        'imported' => 1,
        'updated' => 0,
        'skipped' => 0,
    ]);

    $order = DonationOrder::query()->first();
    expect($order)->not->toBeNull()
        ->and($order->payment_provider)->toBe(DonationOrder::PROVIDER_DANAMOJO)
        ->and($order->source_channel)->toBe(DonationAttributionService::CHANNEL_DANAMOJO)
        ->and($order->provider_order_id)->toBe('danamojo-382670')
        ->and((float) $order->total_amount)->toBe(3858.68)
        ->and($order->currency)->toBe('INR')
        ->and($order->status)->toBe(DonationOrder::STATUS_PAID)
        ->and($order->donor_name)->toBe('sanjay Morzaria')
        ->and($order->donor_country_code)->toBe('GB')
        ->and($order->landing_path)->toBe('/donate/danamojo-widget')
        ->and($order->receipt_number)->not->toBeNull()
        ->and($order->receiptNumberFormatted())->toStartWith('MSCT-DNMJ-');

    $item = $order->items()->first();
    expect($item->cause_id)->toBe($cause->id)
        ->and($item->meta['danamojo']['donation_info_id'])->toBe(382670)
        ->and($item->meta['danamojo']['receipt_number'])->toBe('DM-0000000007');

    $second = $importer->sync(now()->subDay(), now());
    expect($second)->toMatchArray([
        'fetched' => 1,
        'imported' => 0,
        'updated' => 1,
        'skipped' => 0,
    ]);

    expect(DonationOrder::query()->count())->toBe(1);
});

it('skips non-verified danamojo donations', function () {
    config(['danamojo.api_key_secret' => 'test-secret']);

    Http::fake([
        'api.danamojo.org/*' => Http::response([
            'status' => 1,
            'data' => [sampleDanamojoDonation(['paymentStatus' => 'Pending'])],
        ], 200),
    ]);

    $stats = app(DanamojoDonationImporter::class)->sync(now()->subDay(), now());

    expect($stats['skipped'])->toBe(1)
        ->and(DonationOrder::query()->count())->toBe(0);
});

it('runs danamojo sync artisan command', function () {
    config([
        'danamojo.api_key_secret' => 'test-secret',
        'danamojo.queue_sheet_on_import' => false,
    ]);

    Cause::factory()->create([
        'title' => 'Tree Plantation',
        'slug' => 'tree-plantation',
    ]);

    Http::fake([
        'api.danamojo.org/*' => Http::response([
            'status' => 1,
            'data' => [sampleDanamojoDonation()],
        ], 200),
    ]);

    $this->artisan('danamojo:sync', [
        '--from' => '2026-07-20',
        '--to' => '2026-07-25',
    ])->assertSuccessful();

    expect(DonationOrder::query()->count())->toBe(1);
});
