<?php

use App\Support\Attribution\AttributionNormalizer;
use App\Support\Attribution\AttributionTaxonomy;

it('normalizes meta ads with placement medium into meta paid social facebook', function () {
    $result = AttributionNormalizer::normalize([
        'utm_source' => 'meta',
        'utm_medium' => 'Facebook_Desktop_Feed',
        'utm_campaign' => '12/08 | Sadbhavna | Shravan | Ashvini',
        'utm_content' => '12/08 | Sadbhavna | Shravan | Ashvini | Old age 1000',
    ]);

    expect($result['attr_source'])->toBe(AttributionTaxonomy::SOURCE_META)
        ->and($result['attr_medium'])->toBe(AttributionTaxonomy::MEDIUM_PAID_SOCIAL)
        ->and($result['attr_platform'])->toBe(AttributionTaxonomy::PLATFORM_FACEBOOK)
        ->and($result['attr_placement'])->toBe('facebook_desktop_feed');
});

it('normalizes legacy instagram source into meta platform instagram', function () {
    $result = AttributionNormalizer::normalize([
        'utm_source' => 'instagram',
        'utm_medium' => 'paid',
        'utm_campaign' => 'spring',
    ]);

    expect($result['attr_source'])->toBe(AttributionTaxonomy::SOURCE_META)
        ->and($result['attr_platform'])->toBe(AttributionTaxonomy::PLATFORM_INSTAGRAM)
        ->and($result['attr_medium'])->toBe(AttributionTaxonomy::MEDIUM_PAID_SOCIAL);
});

it('normalizes fbclid landing paths into meta paid social', function () {
    $result = AttributionNormalizer::normalize([
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'landing_path' => 'donate/checkout?fbclid=1',
    ]);

    expect($result['attr_source'])->toBe(AttributionTaxonomy::SOURCE_META)
        ->and($result['attr_medium'])->toBe(AttributionTaxonomy::MEDIUM_PAID_SOCIAL)
        ->and($result['attr_platform'])->toBe(AttributionTaxonomy::PLATFORM_FACEBOOK);
});

it('normalizes staff referrals without changing source', function () {
    $result = AttributionNormalizer::normalize([
        'utm_source' => 'staff',
        'utm_medium' => 'referral',
        'utm_content' => 'ac',
    ]);

    expect($result['attr_source'])->toBe(AttributionTaxonomy::SOURCE_STAFF)
        ->and($result['attr_medium'])->toBe(AttributionTaxonomy::MEDIUM_REFERRAL)
        ->and($result['attr_platform'])->toBeNull();
});

it('maps meta site source platform tokens', function () {
    $result = AttributionNormalizer::normalize([
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'platform' => 'ig',
        'placement' => 'Instagram_Reels',
    ]);

    expect($result['attr_platform'])->toBe(AttributionTaxonomy::PLATFORM_INSTAGRAM)
        ->and($result['attr_placement'])->toBe('instagram_reels');
});
