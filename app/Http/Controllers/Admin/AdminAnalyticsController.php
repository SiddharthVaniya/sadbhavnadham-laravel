<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAnalyticsData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $duration = (string) $request->input('duration', 'today');

        if (! array_key_exists($duration, AdminAnalyticsData::DURATION_OPTIONS)) {
            $duration = 'today';
        }

        return Inertia::render('Admin/Analytics/Index', [
            'duration' => $duration,
            'durationOptions' => AdminAnalyticsData::DURATION_OPTIONS,
            ...AdminAnalyticsData::reportFromRequest($request),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $report = AdminAnalyticsData::exportRows($request);
        $filename = 'analytics_'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($report): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['Analytics report', $report['durationLabel']]);
            fputcsv($file, []);

            fputcsv($file, ['Metric', 'Value']);
            foreach ($report['summary'] as $key => $value) {
                fputcsv($file, [$key, $value]);
            }

            fputcsv($file, []);
            fputcsv($file, ['Cause', 'Views', 'Unique visitors', 'Checkouts', 'Paid', 'Revenue', 'Conversion %']);

            foreach ($report['topCauses'] as $cause) {
                fputcsv($file, [
                    $cause['cause'],
                    $cause['views'],
                    $cause['unique_visitors'],
                    $cause['checkouts'],
                    $cause['paid'],
                    $cause['revenue'],
                    $cause['conversion_rate'],
                ]);
            }

            fputcsv($file, []);
            fputcsv($file, ['Referrer', 'Hits']);

            foreach ($report['referrers'] as $referrer) {
                fputcsv($file, [$referrer['referrer'], $referrer['hits']]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
