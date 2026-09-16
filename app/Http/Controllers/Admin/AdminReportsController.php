<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cause;
use App\Support\AdminReportsData;
use App\Support\ReportExporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AdminReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $type = (string) $request->input('type', 'monthly_by_cause');

        if (! array_key_exists($type, AdminReportsData::REPORT_TYPES)) {
            $type = 'monthly_by_cause';
        }

        $filters = AdminReportsData::normalizeFilters($request);
        $filterOptions = AdminReportsData::filterOptions();

        return Inertia::render('Admin/Reports/Index', [
            'type' => $type,
            'duration' => $filters['duration'],
            'reportTypes' => AdminReportsData::REPORT_TYPES,
            'durationOptions' => AdminReportsData::DURATION_OPTIONS,
            'causeMatchOptions' => AdminReportsData::CAUSE_MATCH_OPTIONS,
            'formats' => AdminReportsData::FORMATS,
            'causes' => Cause::query()->orderBy('title')->get(['id', 'title']),
            'filterOptions' => $filterOptions,
            'filters' => [
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
                'cause_id' => $filters['cause_id'],
                'state' => $filters['state'],
                'source' => $filters['source'],
                'partner_user_id' => $filters['partner_user_id'],
                'cause_match' => $filters['cause_match'],
            ],
            'report' => AdminReportsData::reportFromRequest($request),
        ]);
    }

    public function export(Request $request): SymfonyResponse
    {
        $format = strtolower((string) $request->input('format', 'csv'));

        if (! in_array($format, AdminReportsData::FORMATS, true)) {
            abort(422, 'Unsupported export format.');
        }

        $report = AdminReportsData::reportFromRequest($request);

        return ReportExporter::download($report, $format);
    }
}
