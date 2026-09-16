<?php

use App\Helpers\NumberHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a sample new receipt without creating a donation', function () {
    $before = \App\Models\DonationOrder::query()->count();

    $response = $this->get(route('receipts.demo'));

    $response->assertOk();
    $response->assertSee('DONATION RECEIPT', false);
    $response->assertSee('Demo receipt', false);
    $response->assertSee('Tree Plantation', false);
    $response->assertSee('Package: Sapling Pack', false);
    $response->assertSee('john.doe@example.com', false);
    $response->assertSee(NumberHelper::formatInr(1000), false);
    $response->assertSee('Authorized Signatory', false);
    $response->assertSee('AADTM7770L', false);
    $response->assertDontSee('ORGANISATION', false);
    $response->assertDontSee('THANK YOU FOR THE DONATION!', false);

    expect(\App\Models\DonationOrder::query()->count())->toBe($before);
});
