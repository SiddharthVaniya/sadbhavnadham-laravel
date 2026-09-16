<?php

namespace App\Http\Controllers;

use App\Models\Cause;
use App\Models\DonationCampaign;
use App\Services\AnalyticsService;
use App\Support\DonationPublicFrontend;
use App\Support\StaffReferral;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StaffReferralController extends Controller
{
    public function __construct(
        private AnalyticsService $analytics,
        private DonatePageController $donatePages,
    ) {}

    public function home(Request $request, string $referral): View|RedirectResponse
    {
        if (StaffReferral::findUserByCode($referral) === null) {
            abort(404);
        }

        if (DonationPublicFrontend::shouldRedirect($request) || ! StaffReferral::vanityUrlsEnabled()) {
            return $this->redirectToTrackedPath($request, $referral, '/');
        }

        $this->stampAttribution($request, $referral);

        return $this->donatePages->index();
    }

    public function cause(Request $request, string $referral, Cause $cause): Response|RedirectResponse
    {
        if (StaffReferral::findUserByCode($referral) === null) {
            abort(404);
        }

        if (DonationPublicFrontend::shouldRedirect($request) || ! StaffReferral::vanityUrlsEnabled()) {
            return $this->redirectToTrackedPath(
                $request,
                $referral,
                route('donate.show', $cause->slug, false),
            );
        }

        $this->stampAttribution($request, $referral);

        return $this->donatePages->show($cause);
    }

    public function campaign(Request $request, string $referral, DonationCampaign $campaign): Response|RedirectResponse
    {
        if (StaffReferral::findUserByCode($referral) === null) {
            abort(404);
        }

        if (! StaffReferral::vanityUrlsEnabled()) {
            return $this->redirectToTrackedPath(
                $request,
                $referral,
                route('donate.campaign', $campaign->slug, false),
            );
        }

        $this->stampAttribution($request, $referral);

        return $this->donatePages->campaign($campaign);
    }

    private function redirectToTrackedPath(Request $request, string $referral, string $path): RedirectResponse
    {
        $extraQuery = [];

        foreach (['package_id', 'utm_campaign', 'utm_term', 'platform', 'placement', 'sid'] as $key) {
            if ($request->filled($key)) {
                $extraQuery[$key] = $request->query($key);
            }
        }

        $target = StaffReferral::canonicalTrackedUrl($path, $referral, $extraQuery);

        if (DonationPublicFrontend::shouldRedirect($request) && self::pathBelongsOnFrontend($path)) {
            $query = [];
            $queryString = parse_url($target, PHP_URL_QUERY);

            if (is_string($queryString) && $queryString !== '') {
                parse_str($queryString, $query);
            }

            return redirect()->away(
                DonationPublicFrontend::absolute((string) (parse_url($target, PHP_URL_PATH) ?: $path), $query),
                301,
            );
        }

        return redirect()->to($target, 301);
    }

    private static function pathBelongsOnFrontend(string $path): bool
    {
        return $path === '/' || str_starts_with($path, '/donate/');
    }

    private function stampAttribution(Request $request, string $referral): void
    {
        $attribution = StaffReferral::attributionQuery($referral);

        foreach (['package_id', 'utm_campaign'] as $key) {
            if ($request->filled($key)) {
                $attribution[$key] = $request->query($key);
            }
        }

        $this->analytics->rememberUtm($attribution);

        foreach ($attribution as $key => $value) {
            $request->query->set($key, $value);
            $request->request->set($key, $value);
        }

        view()->share('staffReferralCode', StaffReferral::normalize($referral));
    }
}
