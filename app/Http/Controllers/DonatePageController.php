<?php

namespace App\Http\Controllers;

use App\Models\Cause;
use App\Models\DonationCampaign;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Services\AnalyticsService;
use App\Support\AdminCampaignStatsData;
use App\Support\DonationPublicFrontend;
use App\Support\DonationThankYouUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class DonatePageController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index(): View|RedirectResponse
    {
        if ($redirect = DonationPublicFrontend::redirectPreserveQuery(request(), '/')) {
            return $redirect;
        }

        $causes = Cause::query()
            ->where('is_active', true)
            ->listedOnDonateIndex()
            ->orderBy('sort_order')
            ->get();

        $this->analytics->trackVisitHome(request());

        return view('donate.index', [
            'causes' => $causes,
            'suppressIndexing' => request()->filled('donation'),
        ]);
    }

    public function thankYou(DonationOrder $order): RedirectResponse
    {
        return redirect()->away(DonationThankYouUrl::forOrder($order));
    }

    public function thankYouSubscription(DonationSubscription $subscription): RedirectResponse
    {
        return redirect()->away(DonationThankYouUrl::forSubscription($subscription));
    }

    public function show(Cause $cause): Response|RedirectResponse
    {
        if (! $cause->is_active) {
            abort(404);
        }

        if ($redirect = DonationPublicFrontend::redirectPreserveQuery(request(), '/donate/'.$cause->slug)) {
            return $redirect;
        }

        $cause->load([
            'packages' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            },
        ]);

        $this->analytics->trackVisitCause(request(), $cause);

        $defaultPackage = $cause->packages->firstWhere('is_default', true) ?? $cause->packages->first();
        $defaultTitle = $defaultPackage?->title ?? ($cause->default_title ?? 'General Donation');
        $defaultAmount = $defaultPackage?->amount ?? ($cause->default_amount ?? 1);

        $otherCauses = Cause::query()
            ->where('is_active', true)
            ->listedOnDonateIndex()
            ->orderBy('sort_order')
            ->limit(6)
            ->get();

        return response()
            ->view('donate.show', [
                'cause' => $cause,
                'packages' => $cause->packages,
                'defaultPackage' => $defaultPackage,
                'defaultTitle' => $defaultTitle,
                'defaultAmount' => $defaultAmount,
                'otherCauses' => $otherCauses,
            ])
            ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    public function bankDetails(): View
    {
        $this->analytics->captureFromRequest(request());

        return view('donate.bank-details');
    }

    public function campaign(DonationCampaign $campaign): Response
    {
        if (! $campaign->isAvailable()) {
            abort(404);
        }

        $campaign->load(['cause', 'package']);

        if (! $campaign->cause?->is_active) {
            abort(404);
        }

        if (! $campaign->isReadyForCheckout()) {
            abort(404);
        }

        $this->analytics->trackVisitCampaign(request(), $campaign);

        $package = $campaign->resolvedPackage();
        $heroImage = $campaign->resolvedHeroImage();
        $raisedAmount = (float) AdminCampaignStatsData::metricsForCampaign($campaign)['revenue'];
        $goalAmount = $campaign->goal_amount !== null ? (float) $campaign->goal_amount : null;
        $goalProgressPercent = $goalAmount && $goalAmount > 0
            ? min(100, round(($raisedAmount / $goalAmount) * 100, 1))
            : null;

        return response()
            ->view('donate.campaign', [
                'campaign' => $campaign,
                'cause' => $campaign->cause,
                'defaultPackage' => $package,
                'defaultTitle' => $campaign->resolvedTitle(),
                'defaultAmount' => $campaign->resolvedAmount(),
                'heroImage' => $heroImage,
                'goalAmount' => $goalAmount,
                'raisedAmount' => $raisedAmount,
                'goalProgressPercent' => $goalProgressPercent,
            ])
            ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache');
    }
}
