<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsGeoLocator;
use App\Services\AnalyticsService;
use App\Support\LiveVisitorTracker;
use App\Support\MapCoordinateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function heartbeat(
        Request $request,
        AnalyticsService $analytics,
        AnalyticsGeoLocator $geoLocator,
        MapCoordinateResolver $coordinates,
        LiveVisitorTracker $tracker,
    ): JsonResponse {
        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:200'],
        ]);

        $geo = $geoLocator->fromRequest($request);
        $coords = $coordinates->resolve(
            city: isset($geo['city']) ? (string) $geo['city'] : null,
            region: isset($geo['region_name']) ? (string) $geo['region_name'] : null,
            countryCode: isset($geo['country_code']) ? (string) $geo['country_code'] : null,
            countryName: isset($geo['country_name']) ? (string) $geo['country_name'] : null,
            lat: isset($geo['lat']) ? (float) $geo['lat'] : null,
            lng: isset($geo['lng']) ? (float) $geo['lng'] : null,
        );

        $tracker->heartbeat($analytics->sessionId($request), [
            'path' => $validated['path'] ?? '/'.$request->path(),
            'city' => $geo['city'] ?? null,
            'region' => $geo['region_name'] ?? null,
            'country_code' => $geo['country_code'] ?? null,
            'country_name' => $geo['country_name'] ?? null,
            'lat' => $coords['lat'] ?? null,
            'lng' => $coords['lng'] ?? null,
            'label' => $coords['label'] ?? null,
            'precision' => $coords['precision'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }
}
