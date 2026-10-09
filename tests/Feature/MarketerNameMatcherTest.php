<?php

use App\Models\MetaAdAccount;
use App\Models\MetaAdSpendDaily;
use App\Models\User;
use App\Support\MarketerNameMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    MarketerNameMatcher::clearMarketerCache();
});

it('matches marketer name anywhere in the ad string not only the first pipe segment', function () {
    $marketer = User::factory()->create([
        'name' => 'Ashvini Patel',
        'referral_code' => 'ashvini1',
    ]);

    $result = MarketerNameMatcher::resolveAttribution(
        'Old age | Ashvini | Pitru Amas',
        null,
        null,
    );

    expect($result['user_id'])->toBe($marketer->id)
        ->and($result['matched_via'])->toBe(MarketerNameMatcher::MATCHED_VIA_NAME_IN_TEXT);
});

it('matches marketer from campaign name when ad name has no marketer', function () {
    $marketer = User::factory()->create([
        'name' => 'Urvi Soni',
        'referral_code' => 'urvi123',
    ]);

    $result = MarketerNameMatcher::resolveAttribution(
        'Generic creative',
        'Urvi Soni | Monsoon campaign',
        'Ad set A',
    );

    expect($result['user_id'])->toBe($marketer->id)
        ->and($result['matched_via'])->toBe(MarketerNameMatcher::MATCHED_VIA_NAME_IN_TEXT);
});

it('returns unmatched when two marketers share the same first name in text', function () {
    User::factory()->create(['name' => 'Ashvini Patel', 'referral_code' => 'ash1']);
    User::factory()->create(['name' => 'Ashvini Sharma', 'referral_code' => 'ash2']);

    $result = MarketerNameMatcher::resolveAttribution(
        'Ashvini | mixed',
        null,
        null,
    );

    expect($result['user_id'])->toBeNull()
        ->and($result['matched_via'])->toBe(MarketerNameMatcher::MATCHED_VIA_UNMATCHED);
});

it('matches Ashwini ad prefix to Ashvini marketer (common spelling in Ads Manager)', function () {
    $marketer = User::factory()->create([
        'name' => 'Ashvini Patel',
        'referral_code' => 'xvjsrg',
    ]);

    $result = MarketerNameMatcher::resolveAttribution(
        'Ashwini | 09/10 | Sadbhavna | Pitru Amas | Theme',
        null,
        null,
    );

    expect($result['user_id'])->toBe($marketer->id)
        ->and($result['matched_via'])->toBe(MarketerNameMatcher::MATCHED_VIA_AD_NAME_PREFIX);
});

it('uses meta_ad_aliases when set on the user', function () {
    $marketer = User::factory()->create([
        'name' => 'Priya Sharma',
        'referral_code' => 'priya1',
        'meta_ad_aliases' => 'Priyanka,Priya S',
    ]);

    $result = MarketerNameMatcher::resolveAttribution(
        'Priyanka | 09/10 | Brand | Theme | Cause',
        null,
        null,
    );

    expect($result['user_id'])->toBe($marketer->id);
});

it('reattributes stored rows using full string matching', function () {
    $marketer = User::factory()->create([
        'name' => 'Ashvini Patel',
        'referral_code' => 'ashvini1',
    ]);

    $account = MetaAdAccount::query()->create([
        'label' => 'Test',
        'app_id' => '1',
        'app_secret' => 's',
        'access_token' => 't',
        'ad_account_id' => '99',
        'is_active' => true,
    ]);

    MetaAdSpendDaily::query()->create([
        'meta_ad_account_id' => $account->id,
        'spend_date' => now()->toDateString(),
        'ad_id' => 'ad-1',
        'ad_name' => 'Theme | Ashvini | Cause',
        'campaign_name' => 'Camp',
        'spend_amount' => 50,
        'user_id' => null,
        'matched_via' => 'unmatched',
    ]);

    $updated = MarketerNameMatcher::reattributeSnapshotRows();

    expect($updated)->toBe(1);

    $row = MetaAdSpendDaily::query()->first();
    expect($row->user_id)->toBe($marketer->id)
        ->and($row->matched_via)->toBe(MarketerNameMatcher::MATCHED_VIA_NAME_IN_TEXT);
});
