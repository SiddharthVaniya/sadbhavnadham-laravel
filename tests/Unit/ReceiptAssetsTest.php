<?php

namespace Tests\Unit;

use App\Support\ReceiptAssets;
use Tests\TestCase;

class ReceiptAssetsTest extends TestCase
{
    public function test_receipt_asset_urls_use_configured_base_url(): void
    {
        config([
            'receipt.asset_base_url' => 'https://donate.sadbhavnadham.org',
            'receipt.images.logo' => '/assets/img/logo/logo-black.png',
            'receipt.images.photo1' => '/images/reciept/old-age.webp',
        ]);

        $this->assertSame(
            'https://donate.sadbhavnadham.org/assets/img/logo/logo-black.png',
            ReceiptAssets::url('logo')
        );

        $this->assertSame(
            'https://donate.sadbhavnadham.org/images/reciept/old-age.webp',
            ReceiptAssets::url('photo1')
        );
    }
}
