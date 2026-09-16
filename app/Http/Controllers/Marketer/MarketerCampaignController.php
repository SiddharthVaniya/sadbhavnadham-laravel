<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Support\MarketerPerformanceData;
use App\Support\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketerCampaignController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return Inertia::render(
            'Marketer/Campaigns',
            MarketerPerformanceData::campaigns($user, $request),
        );
    }

    public function export(Request $request): Response|StreamedResponse
    {
        $format = strtolower((string) $request->input('format', 'csv'));

        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format.');
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $payload = MarketerPerformanceData::exportCampaigns($user, $request);

        return ReportExporter::downloadTable(
            $payload['title'],
            $payload['periodLabel'],
            $payload['headers'],
            $payload['rows'],
            $format,
        );
    }
}
