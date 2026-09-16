<?php

use App\Support\BrandingStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the bank details page with copyable account fields', function () {
    BrandingStore::persist([
        'bank' => [
            'account_name' => 'Manav Seva Cheritable Trust',
            'account_number' => '065821010000069',
            'ifsc' => 'UBIN0906581',
            'bank_name' => 'Union Bank of India',
        ],
        'contact' => [
            'email' => 'info@sadbhavnadham.org',
        ],
    ]);
    BrandingStore::applyToConfig();

    $response = $this->get(route('donate.bank-details'));

    $response->assertOk();
    $response->assertSee('Bank Details', false);
    $response->assertSee('065821010000069', false);
    $response->assertSee('UBIN0906581', false);
    $response->assertSee('data-copy="065821010000069"', false);
    $response->assertSee('Copy all details', false);
    $response->assertSee('Scan to pay', false);
});

it('shows the main-site style footer with bank details on donate pages', function () {
    BrandingStore::persist([
        'footer' => [
            'about' => 'Founded on August 15, 2015, our NGO runs Vrudhashram.',
        ],
        'bank' => [
            'account_name' => 'Manav Seva Cheritable Trust',
            'account_number' => '065821010000069',
            'ifsc' => 'UBIN0906581',
            'bank_name' => 'Union Bank of India',
        ],
        'contact' => [
            'phone_primary' => '+91 85301 38001',
            'email' => 'info@sadbhavnadham.org',
        ],
    ]);
    BrandingStore::applyToConfig();

    $response = $this->get(route('donate.index'));

    $response->assertOk();
    $response->assertSee('Donate Now', false);
    $response->assertSee('Our Initiative', false);
    $response->assertSee('Contact Us', false);
    $response->assertSee('Useful Links', false);
    $response->assertSee('Initiatives', false);
    $response->assertSee('Stay Connected', false);
    $response->assertSee('+91 85301 38001', false);
    $response->assertSee('065821010000069', false);
    $response->assertSee('UBIN0906581', false);
    $response->assertSee('data-footer-copy="065821010000069"', false);
    $response->assertSee(route('donate.bank-details'), false);
});
