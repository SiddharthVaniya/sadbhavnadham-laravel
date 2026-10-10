<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminInertiaData;
use App\Support\DonationOrderListQuery;
use App\Support\TelecallerPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTelecallerDonationController extends Controller
{
    public function __construct(
        private DonationOrderListQuery $donationOrderListQuery,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && TelecallerPortal::usesFailedDonationWorkflow($user), 403);

        $duration = $this->donationOrderListQuery->resolveTelecallerDuration($request);
        $query = $this->donationOrderListQuery->telecallerFailed($request);

        $totalCount = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum('total_amount');

        [$sort, $dir] = $this->donationOrderListQuery->resolveSort($request);
        $this->donationOrderListQuery->applySort($query, $sort, $dir);

        $donations = $query->paginate(25)->withQueryString();

        $durationOptions = $this->donationOrderListQuery->durationOptions();

        return Inertia::render('Admin/Telecaller/FailedDonations', [
            'donations' => AdminInertiaData::paginatedDonations($donations),
            'duration' => $duration,
            'durationOptions' => $durationOptions,
            'overviewDateLabel' => $this->donationOrderListQuery->overviewDateLabel($request, $duration, $durationOptions),
            'totalCount' => $totalCount,
            'totalAmount' => $totalAmount,
            'sort' => $sort,
            'dir' => $dir,
            'filters' => [
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'search' => $request->input('search'),
            ],
        ]);
    }
}
