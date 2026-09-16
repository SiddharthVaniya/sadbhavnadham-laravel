<?php

namespace App\Support;

use App\Models\DonationCampaign;
use Illuminate\Http\Request;

class CampaignDonationContext
{
    /**
     * @return array{id: int, slug: string, name: string, frequency: string, recurring_only: bool}|null
     */
    public static function fromRequest(Request $request): ?array
    {
        $slug = trim((string) $request->input('campaign_slug', ''));

        if ($slug === '') {
            return null;
        }

        $campaign = DonationCampaign::query()->where('slug', $slug)->first();

        if (! $campaign) {
            return null;
        }

        return [
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'name' => $campaign->name,
            'frequency' => $campaign->billingFrequency(),
            'recurring_only' => (bool) $campaign->recurring_only,
        ];
    }

    public static function findBySlug(?string $slug): ?DonationCampaign
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return null;
        }

        return DonationCampaign::query()->where('slug', $slug)->first();
    }
}
