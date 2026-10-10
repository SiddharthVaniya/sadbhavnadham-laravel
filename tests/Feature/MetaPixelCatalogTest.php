<?php

use App\Support\Meta\MetaPixelCatalog;

it('resolves pixel codes like the next.js site', function () {
    config([
        'meta_capi.default_pixel_code' => 'sadbhavna_d',
        'meta_capi.pixels' => [
            ['code' => 'sadbhavna_d', 'pixel_id' => '1436406881878584'],
            ['code' => 'sadbhavna_1', 'pixel_id' => '968382969596671'],
        ],
    ]);

    $fromUrl = MetaPixelCatalog::resolve('sadbhavna_1', 'sadbhavna_d');
    expect($fromUrl['code'])->toBe('sadbhavna_1')
        ->and($fromUrl['id'])->toBe('968382969596671');

    $fromStored = MetaPixelCatalog::resolve(null, 'sadbhavna_1');
    expect($fromStored['id'])->toBe('968382969596671');

    $default = MetaPixelCatalog::resolve(null, null);
    expect($default['code'])->toBe('sadbhavna_d')
        ->and($default['id'])->toBe('1436406881878584');
});

it('reads pixel_id from landing_url when stamping checkout', function () {
    config([
        'meta_capi.default_pixel_code' => 'sadbhavna_d',
        'meta_capi.pixels' => [
            ['code' => 'sadbhavna_d', 'pixel_id' => '1436406881878584'],
            ['code' => 'sadbhavna_1', 'pixel_id' => '968382969596671'],
        ],
    ]);

    $code = MetaPixelCatalog::resolveCodeFromCheckoutSnapshot([
        'landing_url' => 'https://sadbhavnadham.org/donate/tree?pixel_id=sadbhavna_1&sid=ashvini',
    ]);

    expect($code)->toBe('sadbhavna_1');
});
