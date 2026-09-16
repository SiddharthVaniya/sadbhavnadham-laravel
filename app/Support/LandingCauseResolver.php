<?php

namespace App\Support;

use App\Models\Cause;
use App\Models\DonationCampaign;
use Illuminate\Support\Str;

class LandingCauseResolver
{
    /**
     * Path segments that look like cause slugs but are not causes.
     *
     * @var list<string>
     */
    public const RESERVED_SEGMENTS = [
        'checkout',
        'success',
        'failed',
        'pending',
        'danamojo-widget',
        'recurring',
        'subscribe',
        'subscription',
        'api',
        'cart',
        'thank-you',
        'thankyou',
    ];

    public static function slugFromLandingPath(?string $landingPath): ?string
    {
        if (! filled($landingPath)) {
            return null;
        }

        $path = self::pathOnly($landingPath);
        if ($path === null || $path === '/') {
            return null;
        }

        $patterns = [
            '#/(?:donate|causes)/danamojo-widget/([a-z0-9-]+)(?:/|$)#i',
            '#/(?:donate|causes)/([a-z0-9-]+)(?:/|$)#i',
            '#^/[a-z0-9-]+/donate/([a-z0-9-]+)(?:/|$)#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $matches) !== 1) {
                continue;
            }

            $slug = Str::lower($matches[1]);
            if (in_array($slug, self::RESERVED_SEGMENTS, true)) {
                return null;
            }

            return $slug;
        }

        return null;
    }

    public static function campaignSlugFromLandingPath(?string $landingPath): ?string
    {
        if (! filled($landingPath)) {
            return null;
        }

        $path = self::pathOnly($landingPath);
        if ($path === null) {
            return null;
        }

        if (preg_match('#/give/([a-z0-9-]+)(?:/|$)#i', $path, $matches) !== 1) {
            return null;
        }

        return Str::lower($matches[1]);
    }

    public static function causeIdFromLandingPath(?string $landingPath): ?int
    {
        $slug = self::slugFromLandingPath($landingPath);
        if ($slug !== null) {
            $causeId = Cause::query()->where('slug', $slug)->value('id');

            return $causeId ? (int) $causeId : null;
        }

        $campaignSlug = self::campaignSlugFromLandingPath($landingPath);
        if ($campaignSlug === null) {
            return null;
        }

        $causeId = DonationCampaign::query()
            ->where('slug', $campaignSlug)
            ->whereNotNull('cause_id')
            ->value('cause_id');

        return $causeId ? (int) $causeId : null;
    }

    /**
     * SQL LIKE patterns that indicate a landing path for the given cause slug.
     *
     * @return list<string>
     */
    public static function likePatternsForSlug(string $slug): array
    {
        $escaped = addcslashes($slug, '%_\\');

        return [
            '%/donate/'.$escaped,
            '%/donate/'.$escaped.'/%',
            '%/donate/'.$escaped.'?%',
            '%/causes/'.$escaped,
            '%/causes/'.$escaped.'/%',
            '%/causes/'.$escaped.'?%',
            '%/donate/danamojo-widget/'.$escaped,
            '%/donate/danamojo-widget/'.$escaped.'/%',
            '%/donate/danamojo-widget/'.$escaped.'?%',
            'donate/'.$escaped,
            'donate/'.$escaped.'/%',
            'donate/'.$escaped.'?%',
            'causes/'.$escaped,
            'causes/'.$escaped.'/%',
            'causes/'.$escaped.'?%',
        ];
    }

    private static function pathOnly(string $landingPath): ?string
    {
        $raw = trim($landingPath);
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, '://')) {
            $parsed = parse_url($raw, PHP_URL_PATH);
            $path = is_string($parsed) ? $parsed : '/';
        } else {
            $path = strtok($raw, '?#') ?: '/';
        }

        $path = '/'.ltrim(str_replace('\\', '/', $path), '/');

        return $path === '//' ? '/' : $path;
    }
}
