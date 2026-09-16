<?php

use App\Models\Cause;
use App\Models\DonationCampaign;
use App\Support\LandingCauseResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves cause slug from donate and causes landing paths', function () {
    expect(LandingCauseResolver::slugFromLandingPath('/donate/old-age-home?utm_source=meta'))
        ->toBe('old-age-home')
        ->and(LandingCauseResolver::slugFromLandingPath('/causes/tree-plantation'))
        ->toBe('tree-plantation')
        ->and(LandingCauseResolver::slugFromLandingPath('donate/tree-plantation?sid=ashvini'))
        ->toBe('tree-plantation')
        ->and(LandingCauseResolver::slugFromLandingPath('/ashvini/donate/old-age-home'))
        ->toBe('old-age-home')
        ->and(LandingCauseResolver::slugFromLandingPath('/donate/checkout'))
        ->toBeNull()
        ->and(LandingCauseResolver::slugFromLandingPath('/'))
        ->toBeNull();
});

it('resolves landing cause id from path and give campaign', function () {
    $cause = Cause::factory()->create([
        'slug' => 'old-age-home',
        'title' => 'Old Age Home',
    ]);

    $campaign = DonationCampaign::factory()->create([
        'slug' => 'monsoon-drive',
        'cause_id' => $cause->id,
        'name' => 'Monsoon Drive',
    ]);

    expect(LandingCauseResolver::causeIdFromLandingPath('/donate/old-age-home'))
        ->toBe($cause->id)
        ->and(LandingCauseResolver::causeIdFromLandingPath('/give/'.$campaign->slug))
        ->toBe($cause->id)
        ->and(LandingCauseResolver::causeIdFromLandingPath('/page'))
        ->toBeNull();
});
