<?php

namespace App\Support;

use App\Models\AnalyticsDailyCause;
use App\Models\AnalyticsDailyDimension;
use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsEvent;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\User;
use App\Services\DonationAttributionService;
use App\Support\Attribution\AttributionTaxonomy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminAnalyticsData
{
    public const DURATION_OPTIONS = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'all' => 'All time',
        'custom' => 'Custom range',
    ];

    /** @var array<string, bool> */
    private static array $rollupCoverageCache = [];

    public static function reportFromRequest(Request $request): array
    {
        $duration = (string) $request->input('duration', 'today');

        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = 'today';
        }

        $range = self::resolveRange($request, $duration);

        return self::buildReport(
            $range,
            $duration,
            $request->boolean('india_focus'),
            $request->filled('from_date') ? (string) $request->input('from_date') : null,
            $request->filled('to_date') ? (string) $request->input('to_date') : null,
        );
    }

    public static function report(string $duration = 'today', bool $indiaFocus = false): array
    {
        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = 'today';
        }

        $range = self::rangeForDuration($duration);

        return self::buildReport($range, $duration, $indiaFocus, null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportRows(Request $request): array
    {
        $report = self::reportFromRequest($request);

        return [
            'summary' => $report['summary'],
            'topCauses' => $report['topCauses'],
            'referrers' => $report['referrers'],
            'durationLabel' => $report['durationLabel'],
        ];
    }

    /**
     * @return array{start: ?Carbon, end: ?Carbon, label: string}
     */
    public static function resolveRange(Request $request, string $duration): array
    {
        if ($duration === 'custom') {
            if ($request->filled('from_date') && $request->filled('to_date')) {
                $start = Carbon::parse((string) $request->input('from_date'))->startOfDay();
                $end = Carbon::parse((string) $request->input('to_date'))->endOfDay();

                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                return [
                    'start' => $start,
                    'end' => $end,
                    'label' => $start->format('d M Y').' – '.$end->format('d M Y'),
                ];
            }

            return self::rangeForDuration('30d');
        }

        return self::rangeForDuration($duration);
    }

    /**
     * @return array{start: ?Carbon, end: ?Carbon, label: string}
     */
    public static function rangeForDuration(string $duration): array
    {
        $end = now()->endOfDay();

        return match ($duration) {
            'today' => [
                'start' => now()->startOfDay(),
                'end' => $end,
                'label' => 'Today',
            ],
            '7d' => [
                'start' => now()->subDays(6)->startOfDay(),
                'end' => $end,
                'label' => 'Last 7 days',
            ],
            '30d' => [
                'start' => now()->subDays(29)->startOfDay(),
                'end' => $end,
                'label' => 'Last 30 days',
            ],
            '90d' => [
                'start' => now()->subDays(89)->startOfDay(),
                'end' => $end,
                'label' => 'Last 90 days',
            ],
            default => [
                'start' => null,
                'end' => null,
                'label' => 'All time',
            ],
        };
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @return array<string, mixed>
     */
    private static function buildReport(
        array $range,
        string $duration,
        bool $indiaFocus,
        ?string $fromDate,
        ?string $toDate,
    ): array {
        $ttl = max(0, (int) config('analytics.admin_report_cache_ttl', 300));

        if ($ttl === 0) {
            return self::computeReport($range, $duration, $indiaFocus, $fromDate, $toDate);
        }

        $cacheKey = 'admin.analytics.report.'.hash('xxh3', json_encode([
            'duration' => $duration,
            'india_focus' => $indiaFocus,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'start' => $range['start']?->toDateString(),
            'end' => $range['end']?->toDateString(),
            'label' => $range['label'],
        ]));

        return Cache::remember($cacheKey, $ttl, fn () => self::computeReport(
            $range,
            $duration,
            $indiaFocus,
            $fromDate,
            $toDate,
        ));
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @return array<string, mixed>
     */
    private static function computeReport(
        array $range,
        string $duration,
        bool $indiaFocus,
        ?string $fromDate,
        ?string $toDate,
    ): array {
        self::$rollupCoverageCache = [];

        $start = $range['start'];
        $end = $range['end'];

        $summary = self::buildSummary($start, $end, $indiaFocus);
        $previous = self::previousRange($start, $end);
        $previousSummary = $previous
            ? self::buildSummary($previous['start'], $previous['end'], $indiaFocus)
            : null;

        return [
            'duration' => $duration,
            'durationLabel' => $range['label'],
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'india_focus' => $indiaFocus,
            ],
            'updatedAt' => now()->format('d M Y, h:i A'),
            'summary' => $summary,
            'comparison' => self::buildComparison($summary, $previousSummary),
            'funnel' => [
                ['label' => 'Unique visitors', 'count' => $summary['unique_visitors']],
                ['label' => 'Unique cause visitors', 'count' => $summary['unique_cause_visitors']],
                ['label' => 'Checkouts started', 'count' => $summary['checkouts_started']],
                ['label' => 'Paid donations', 'count' => $summary['donations_paid']],
            ],
            'dailyTrend' => self::dailyTrend($start, $end, $indiaFocus),
            'topCauses' => self::topCauses($start, $end, $fromDate, $toDate, $duration),
            'topPackages' => self::topPackages($start, $end),
            'referrers' => self::topReferrers($start, $end, $indiaFocus),
            'utmSources' => self::topUtmSources($start, $end, $indiaFocus),
            'paidByChannel' => self::paidByChannel($start, $end),
            'paidByUtm' => self::paidByUtm($start, $end),
            'paidByEmployee' => self::paidByUtmContent($start, $end),
            'paidByPartner' => self::paidByPartner($start, $end),
            'paidByMetaAd' => self::paidByMetaAd($start, $end),
            'paidByReferrer' => self::paidByReferrer($start, $end),
            'devices' => self::deviceBreakdown($start, $end, $indiaFocus),
            'failedPayments' => self::failedPayments($start, $end),
            'abandonedCheckouts' => self::abandonedCheckouts($start, $end),
            'subscriptions' => self::subscriptionMetrics($start, $end),
            'donors' => self::donorMetrics($start, $end),
            'hourlyActivity' => self::hourlyActivity($start, $end, $indiaFocus),
            'locations' => self::locationBreakdown($start, $end, $indiaFocus),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private static function buildSummary(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        if (self::rollupsCoverRange($start, $end)) {
            return self::buildSummaryFromRollups($start, $end, $indiaFocus);
        }

        return self::buildSummaryFromEvents($start, $end, $indiaFocus);
    }

    /**
     * @return array<string, int|float>
     */
    private static function buildSummaryFromRollups(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        $scope = self::rollupScope($indiaFocus);
        $bounds = self::rollupDateBounds($start, $end);

        $totals = self::constrainRollupDates(
            AnalyticsDailyStat::query()->where('scope', $scope),
            $bounds,
        )
            ->selectRaw('COALESCE(SUM(cause_views), 0) as cause_views')
            ->selectRaw('COALESCE(SUM(checkouts_started), 0) as checkouts_started')
            ->selectRaw('COALESCE(SUM(donations_paid), 0) as donations_paid')
            ->selectRaw('COALESCE(SUM(donations_failed), 0) as donations_failed')
            ->selectRaw('COALESCE(SUM(tracked_revenue), 0) as tracked_revenue')
            ->first();

        $uniqueVisitors = self::rollupUniqueVisitors($bounds, $scope, false);
        $uniqueCauseVisitors = self::rollupUniqueVisitors($bounds, $scope, true);

        $countriesReached = (int) self::constrainRollupDates(
            AnalyticsDailyDimension::query()
                ->where('scope', $scope)
                ->where('dimension_type', AnalyticsDailyDimension::TYPE_COUNTRY),
            $bounds,
        )
            ->distinct()
            ->count('dimension_key');

        $causeViews = (int) ($totals->cause_views ?? 0);
        $checkoutsStarted = (int) ($totals->checkouts_started ?? 0);
        $donationsPaid = (int) ($totals->donations_paid ?? 0);
        $donationsFailed = (int) ($totals->donations_failed ?? 0);
        $trackedRevenue = (float) ($totals->tracked_revenue ?? 0);
        $checkoutAttempts = $donationsPaid + $donationsFailed;

        $ordersQuery = DonationOrder::query()->where('status', DonationOrder::STATUS_PAID);

        if ($start && $end) {
            $ordersQuery->whereBetween('paid_at', [$start, $end]);
        }

        $ordersPaid = (int) (clone $ordersQuery)->count();
        $ordersRevenue = (float) (clone $ordersQuery)->sum('total_amount');
        $averageDonation = $ordersPaid > 0 ? round($ordersRevenue / $ordersPaid, 2) : 0.0;

        return [
            'unique_visitors' => $uniqueVisitors,
            'cause_views' => $causeViews,
            'unique_cause_visitors' => $uniqueCauseVisitors,
            'checkouts_started' => $checkoutsStarted,
            'donations_paid' => $donationsPaid,
            'donations_failed' => $donationsFailed,
            'tracked_revenue' => $trackedRevenue,
            'orders_paid' => $ordersPaid,
            'orders_revenue' => $ordersRevenue,
            'average_donation' => $averageDonation,
            'visitor_to_paid_rate' => self::rate($donationsPaid, $uniqueVisitors),
            'cause_to_checkout_rate' => self::rate($checkoutsStarted, $uniqueCauseVisitors),
            'checkout_to_paid_rate' => self::rate($donationsPaid, $checkoutsStarted),
            'payment_success_rate' => self::rate($donationsPaid, $checkoutAttempts),
            'countries_reached' => $countriesReached,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private static function buildSummaryFromEvents(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        $query = self::scopedEventsQuery($start, $end, $indiaFocus);

        $uniqueVisitors = self::distinctSessions(
            (clone $query)->whereIn('event_type', [
                AnalyticsEvent::TYPE_VISIT_HOME,
                AnalyticsEvent::TYPE_VISIT_CAUSE,
            ])
        );

        $causeViews = (clone $query)
            ->where('event_type', AnalyticsEvent::TYPE_VISIT_CAUSE)
            ->count();

        $uniqueCauseVisitors = self::distinctSessions(
            (clone $query)->where('event_type', AnalyticsEvent::TYPE_VISIT_CAUSE)
        );

        $checkoutsStarted = (clone $query)
            ->where('event_type', AnalyticsEvent::TYPE_CHECKOUT_STARTED)
            ->count();

        $donationsPaid = (clone $query)
            ->where('event_type', AnalyticsEvent::TYPE_DONATION_PAID)
            ->count();

        $donationsFailed = (clone $query)
            ->where('event_type', AnalyticsEvent::TYPE_DONATION_FAILED)
            ->count();

        $trackedRevenue = (float) (clone $query)
            ->where('event_type', AnalyticsEvent::TYPE_DONATION_PAID)
            ->sum('amount');

        $checkoutAttempts = $donationsPaid + $donationsFailed;

        $ordersQuery = DonationOrder::query()->where('status', DonationOrder::STATUS_PAID);

        if ($start && $end) {
            $ordersQuery->whereBetween('paid_at', [$start, $end]);
        }

        $ordersPaid = (int) (clone $ordersQuery)->count();
        $ordersRevenue = (float) (clone $ordersQuery)->sum('total_amount');
        $averageDonation = $ordersPaid > 0 ? round($ordersRevenue / $ordersPaid, 2) : 0.0;

        return [
            'unique_visitors' => $uniqueVisitors,
            'cause_views' => $causeViews,
            'unique_cause_visitors' => $uniqueCauseVisitors,
            'checkouts_started' => $checkoutsStarted,
            'donations_paid' => $donationsPaid,
            'donations_failed' => $donationsFailed,
            'tracked_revenue' => $trackedRevenue,
            'orders_paid' => $ordersPaid,
            'orders_revenue' => $ordersRevenue,
            'average_donation' => $averageDonation,
            'visitor_to_paid_rate' => self::rate($donationsPaid, $uniqueVisitors),
            'cause_to_checkout_rate' => self::rate($checkoutsStarted, $uniqueCauseVisitors),
            'checkout_to_paid_rate' => self::rate($donationsPaid, $checkoutsStarted),
            'payment_success_rate' => self::rate($donationsPaid, $checkoutAttempts),
            'countries_reached' => self::distinctCountries($start, $end, $indiaFocus),
        ];
    }

    private static function rollupScope(bool $indiaFocus): string
    {
        return $indiaFocus ? AnalyticsDailyStat::SCOPE_IN : AnalyticsDailyStat::SCOPE_ALL;
    }

    /**
     * @return array{start: string, end: string}|null
     */
    private static function rollupDateBounds(?Carbon $start, ?Carbon $end): ?array
    {
        if ($start && $end) {
            return [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ];
        }

        return null;
    }

    /**
     * Apply inclusive calendar-date filters for rollup tables.
     *
     * Avoid whereBetween on date-cast columns: in non-UTC app timezones Laravel
     * shifts the bounds and silently drops edge days, forcing a full events scan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     * @param  array{start: string, end: string}|null  $bounds
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder
     */
    private static function constrainRollupDates($query, ?array $bounds)
    {
        if (! $bounds) {
            return $query;
        }

        return $query
            ->whereDate('stat_date', '>=', $bounds['start'])
            ->whereDate('stat_date', '<=', $bounds['end']);
    }

    private static function rollupsCoverRange(?Carbon $start, ?Carbon $end): bool
    {
        $cacheKey = ($start?->toDateTimeString() ?? 'null').'|'.($end?->toDateTimeString() ?? 'null');

        if (array_key_exists($cacheKey, self::$rollupCoverageCache)) {
            return self::$rollupCoverageCache[$cacheKey];
        }

        return self::$rollupCoverageCache[$cacheKey] = self::computeRollupsCoverRange($start, $end);
    }

    private static function computeRollupsCoverRange(?Carbon $start, ?Carbon $end): bool
    {
        if (! config('analytics.rollup_enabled', true)) {
            return false;
        }

        $boundsStart = $start?->copy()->startOfDay();
        $boundsEnd = $end?->copy()->startOfDay();
        $yesterday = now()->copy()->subDay()->startOfDay();

        if (! $boundsStart || ! $boundsEnd) {
            $rollupMin = AnalyticsDailyStat::query()
                ->where('scope', AnalyticsDailyStat::SCOPE_ALL)
                ->min('stat_date');

            if (! $rollupMin) {
                return false;
            }

            $boundsStart = Carbon::parse($rollupMin)->startOfDay();
            $boundsEnd = $yesterday->copy();
        }

        // Do not require today's rollup — hourly job may lag and forcing a full
        // analytics_events scan for ~150k+ rows makes the dashboard unusable.
        if ($boundsEnd->gt($yesterday)) {
            $boundsEnd = $yesterday->copy();
        }

        if ($boundsStart->gt($boundsEnd)) {
            return AnalyticsDailyStat::query()
                ->where('scope', AnalyticsDailyStat::SCOPE_ALL)
                ->whereDate('stat_date', now()->toDateString())
                ->exists();
        }

        $expectedDays = max(1, (int) round($boundsStart->diffInDays($boundsEnd)) + 1);
        $actualDays = (int) self::constrainRollupDates(
            AnalyticsDailyStat::query()->where('scope', AnalyticsDailyStat::SCOPE_ALL),
            [
                'start' => $boundsStart->toDateString(),
                'end' => $boundsEnd->toDateString(),
            ],
        )->count();

        return $actualDays >= $expectedDays;
    }

    /**
     * @param  array{start: string, end: string}|null  $bounds
     */
    private static function rollupUniqueVisitors(?array $bounds, string $scope, bool $causeOnly): int
    {
        // Sum of daily uniques (fast). Cross-day de-duplication via session_day
        // is too expensive on large traffic windows.
        $column = $causeOnly ? 'unique_cause_visitors' : 'unique_visitors';

        return (int) self::constrainRollupDates(
            AnalyticsDailyStat::query()->where('scope', $scope),
            $bounds,
        )->sum($column);
    }

    /**
     * @param  array<string, int|float>  $summary
     * @param  array<string, int|float>|null  $previousSummary
     * @return array<string, mixed>
     */
    private static function buildComparison(array $summary, ?array $previousSummary): array
    {
        if ($previousSummary === null) {
            return [
                'available' => false,
                'metrics' => [],
            ];
        }

        return [
            'available' => true,
            'metrics' => [
                self::comparisonMetric('unique_visitors', 'Unique visitors', $summary, $previousSummary),
                self::comparisonMetric('donations_paid', 'Paid donations', $summary, $previousSummary),
                self::comparisonMetric('orders_revenue', 'Order revenue', $summary, $previousSummary),
                self::comparisonMetric('checkouts_started', 'Checkouts started', $summary, $previousSummary),
            ],
        ];
    }

    /**
     * @param  array<string, int|float>  $summary
     * @param  array<string, int|float>  $previousSummary
     * @return array<string, mixed>
     */
    private static function comparisonMetric(
        string $key,
        string $label,
        array $summary,
        array $previousSummary,
    ): array {
        $current = (float) ($summary[$key] ?? 0);
        $previous = (float) ($previousSummary[$key] ?? 0);
        $change = $current - $previous;
        $changePercent = $previous > 0
            ? round(($change / $previous) * 100, 1)
            : ($current > 0 ? 100.0 : 0.0);

        return [
            'key' => $key,
            'label' => $label,
            'current' => $current,
            'previous' => $previous,
            'change' => $change,
            'change_percent' => $changePercent,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}|null
     */
    private static function previousRange(?Carbon $start, ?Carbon $end): ?array
    {
        if (! $start || ! $end) {
            return null;
        }

        $days = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $previousEnd = $start->copy()->subDay()->endOfDay();

        return [
            'start' => $previousEnd->copy()->subDays($days - 1)->startOfDay(),
            'end' => $previousEnd,
        ];
    }

    private static function scopedEventsQuery(?Carbon $start, ?Carbon $end, bool $indiaFocus)
    {
        $query = AnalyticsEvent::query();

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($indiaFocus) {
            $query->where(function ($inner): void {
                $inner->where('country_code', 'IN')
                    ->orWhereNull('country_code');
            });
        }

        return $query;
    }

    private static function distinctSessions($query): int
    {
        return (int) (clone $query)
            ->whereNotNull('session_id')
            ->distinct()
            ->count('session_id');
    }

    private static function rate(int|float $numerator, int|float $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(((float) $numerator / (float) $denominator) * 100, 1);
    }

    private static function dailyTrend(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        if (! $start || ! $end) {
            return self::monthlyTrend(now()->subMonths(11)->startOfMonth(), now()->endOfDay(), $indiaFocus);
        }

        $days = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);

        if ($days > 31) {
            return self::monthlyTrend($start, $end, $indiaFocus);
        }

        if (self::rollupsCoverRange($start, $end)) {
            return self::dailyTrendFromRollups($start, $end, $indiaFocus);
        }

        $rows = self::scopedEventsQuery($start, $end, $indiaFocus)
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as visitors', [AnalyticsEvent::TYPE_VISIT_CAUSE])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as checkouts', [AnalyticsEvent::TYPE_CHECKOUT_STARTED])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as paid', [AnalyticsEvent::TYPE_DONATION_PAID])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $points = [];
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor <= $endDay) {
            $key = $cursor->format('Y-m-d');
            $row = $rows->get($key);

            $points[] = [
                'key' => $key,
                'label' => $cursor->format('d M'),
                'visitors' => (int) ($row->visitors ?? 0),
                'checkouts' => (int) ($row->checkouts ?? 0),
                'paid' => (int) ($row->paid ?? 0),
            ];

            $cursor->addDay();
        }

        return $points;
    }

    private static function dailyTrendFromRollups(Carbon $start, Carbon $end, bool $indiaFocus): array
    {
        $scope = self::rollupScope($indiaFocus);
        $rows = self::constrainRollupDates(
            AnalyticsDailyStat::query()->where('scope', $scope),
            [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
        )
            ->get()
            ->keyBy(fn (AnalyticsDailyStat $row) => $row->stat_date->toDateString());

        $points = [];
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor <= $endDay) {
            $key = $cursor->format('Y-m-d');
            $row = $rows->get($key);

            $points[] = [
                'key' => $key,
                'label' => $cursor->format('d M'),
                'visitors' => (int) ($row->cause_views ?? 0),
                'checkouts' => (int) ($row->checkouts_started ?? 0),
                'paid' => (int) ($row->donations_paid ?? 0),
            ];

            $cursor->addDay();
        }

        return $points;
    }

    private static function monthlyTrend(Carbon $start, Carbon $end, bool $indiaFocus): array
    {
        if (self::rollupsCoverRange($start, $end)) {
            $scope = self::rollupScope($indiaFocus);
            $driver = DB::connection()->getDriverName();
            $monthExpression = $driver === 'sqlite'
                ? "strftime('%Y-%m', stat_date)"
                : "DATE_FORMAT(stat_date, '%Y-%m')";

            $rows = self::constrainRollupDates(
                AnalyticsDailyStat::query()->where('scope', $scope),
                [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ],
            )
                ->selectRaw("{$monthExpression} as month_key")
                ->selectRaw('SUM(cause_views) as visitors')
                ->selectRaw('SUM(checkouts_started) as checkouts')
                ->selectRaw('SUM(donations_paid) as paid')
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->keyBy('month_key');
        } else {
            $driver = DB::connection()->getDriverName();
            $monthExpression = $driver === 'sqlite'
                ? "strftime('%Y-%m', created_at)"
                : "DATE_FORMAT(created_at, '%Y-%m')";

            $rows = self::scopedEventsQuery($start, $end, $indiaFocus)
                ->selectRaw("{$monthExpression} as month_key")
                ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as visitors', [AnalyticsEvent::TYPE_VISIT_CAUSE])
                ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as checkouts', [AnalyticsEvent::TYPE_CHECKOUT_STARTED])
                ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as paid', [AnalyticsEvent::TYPE_DONATION_PAID])
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->keyBy('month_key');
        }

        $points = [];
        $cursor = $start->copy()->startOfMonth();
        $lastMonth = $end->copy()->startOfMonth();

        while ($cursor <= $lastMonth) {
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);

            $points[] = [
                'key' => $key,
                'label' => $cursor->format('M Y'),
                'visitors' => (int) ($row->visitors ?? 0),
                'checkouts' => (int) ($row->checkouts ?? 0),
                'paid' => (int) ($row->paid ?? 0),
            ];

            $cursor->addMonth();
        }

        return $points;
    }

    private static function topCauses(
        ?Carbon $start,
        ?Carbon $end,
        ?string $fromDate,
        ?string $toDate,
        string $duration = 'today',
    ): array {
        if (self::rollupsCoverRange($start, $end)) {
            $scope = self::rollupScope(false);
            $bounds = self::rollupDateBounds($start, $end);

            $rows = AnalyticsDailyCause::query()
                ->where('scope', $scope)
                ->tap(fn ($query) => self::constrainRollupDates($query, $bounds))
                ->select('cause_id')
                ->selectRaw('SUM(views) as views')
                ->selectRaw('SUM(unique_visitors) as unique_visitors')
                ->selectRaw('SUM(checkouts) as checkouts')
                ->selectRaw('SUM(paid) as paid')
                ->selectRaw('SUM(revenue) as revenue')
                ->groupBy('cause_id')
                ->orderByDesc('paid')
                ->limit(10)
                ->get();
        } else {
            $query = AnalyticsEvent::query()
                ->whereNotNull('cause_id')
                ->select('cause_id')
                ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as views', [AnalyticsEvent::TYPE_VISIT_CAUSE])
                ->selectRaw('COUNT(DISTINCT CASE WHEN event_type = ? THEN session_id END) as unique_visitors', [AnalyticsEvent::TYPE_VISIT_CAUSE])
                ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as checkouts', [AnalyticsEvent::TYPE_CHECKOUT_STARTED])
                ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as paid', [AnalyticsEvent::TYPE_DONATION_PAID])
                ->selectRaw('SUM(CASE WHEN event_type = ? THEN amount ELSE 0 END) as revenue', [AnalyticsEvent::TYPE_DONATION_PAID]);

            if ($start && $end) {
                $query->whereBetween('created_at', [$start, $end]);
            }

            $rows = $query
                ->groupBy('cause_id')
                ->orderByDesc('paid')
                ->limit(10)
                ->get();
        }

        $causes = Cause::query()
            ->whereIn('id', $rows->pluck('cause_id'))
            ->pluck('title', 'id');

        return $rows->map(function ($row) use ($causes, $fromDate, $toDate, $duration) {
            $donationFilters = ['cause_id' => $row->cause_id];

            if ($fromDate && $toDate) {
                $donationFilters['from_date'] = $fromDate;
                $donationFilters['to_date'] = $toDate;
            } else {
                $donationFilters['duration'] = $duration;
            }

            return [
                'cause_id' => (int) $row->cause_id,
                'cause' => $causes[$row->cause_id] ?? 'Unknown',
                'views' => (int) $row->views,
                'unique_visitors' => (int) $row->unique_visitors,
                'checkouts' => (int) $row->checkouts,
                'paid' => (int) $row->paid,
                'revenue' => (float) $row->revenue,
                'conversion_rate' => self::rate((int) $row->paid, (int) $row->unique_visitors),
                'donations_url' => route('admin.donations.index', $donationFilters),
            ];
        })->values()->all();
    }

    private static function topPackages(?Carbon $start, ?Carbon $end): array
    {
        $query = DonationItem::query()
            ->join('donation_orders', 'donation_items.donation_order_id', '=', 'donation_orders.id')
            ->where('donation_orders.status', DonationOrder::STATUS_PAID)
            ->whereNotNull('donation_items.cause_package_id');

        if ($start && $end) {
            $query->whereBetween('donation_orders.paid_at', [$start, $end]);
        }

        $rows = $query
            ->select('donation_items.cause_package_id')
            ->selectRaw('COUNT(DISTINCT donation_orders.id) as paid_orders')
            ->selectRaw('SUM(donation_items.amount) as revenue')
            ->groupBy('donation_items.cause_package_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $packages = CausePackage::query()
            ->whereIn('id', $rows->pluck('cause_package_id'))
            ->with('cause:id,title')
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($packages) {
            $package = $packages->get($row->cause_package_id);

            return [
                'package' => $package?->title ?? 'Unknown package',
                'cause' => $package?->cause?->title ?? '—',
                'paid_orders' => (int) $row->paid_orders,
                'revenue' => (float) $row->revenue,
            ];
        })->values()->all();
    }

    private static function topReferrers(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        if (self::rollupsCoverRange($start, $end)) {
            $scope = self::rollupScope($indiaFocus);
            $bounds = self::rollupDateBounds($start, $end);

            return AnalyticsDailyDimension::query()
                ->where('scope', $scope)
                ->where('dimension_type', AnalyticsDailyDimension::TYPE_REFERRER)
                ->tap(fn ($query) => self::constrainRollupDates($query, $bounds))
                ->select('dimension_key')
                ->selectRaw('SUM(hits) as hits')
                ->groupBy('dimension_key')
                ->orderByDesc('hits')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'referrer' => $row->dimension_key,
                    'hits' => (int) $row->hits,
                ])
                ->values()
                ->all();
        }

        $hostExpression = self::referrerHostSqlExpression();

        $rows = self::visitorEventsQuery($start, $end, $indiaFocus)
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->selectRaw("{$hostExpression} as host")
            ->selectRaw('COUNT(*) as hits')
            ->groupByRaw($hostExpression)
            ->orderByDesc('hits')
            ->limit(40)
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $host = self::normalizeReferrerHost(
                is_string($row->host) && $row->host !== ''
                    ? 'https://'.$row->host
                    : null
            ) ?? 'direct';

            $grouped[$host] = ($grouped[$host] ?? 0) + (int) $row->hits;
        }

        arsort($grouped);

        return collect($grouped)
            ->take(8)
            ->map(fn (int $hits, string $referrer) => [
                'referrer' => $referrer,
                'hits' => $hits,
            ])
            ->values()
            ->all();
    }

    private static function topUtmSources(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        if (self::rollupsCoverRange($start, $end)) {
            $scope = self::rollupScope($indiaFocus);
            $bounds = self::rollupDateBounds($start, $end);

            return AnalyticsDailyDimension::query()
                ->where('scope', $scope)
                ->where('dimension_type', AnalyticsDailyDimension::TYPE_UTM)
                ->tap(fn ($query) => self::constrainRollupDates($query, $bounds))
                ->select('dimension_key')
                ->selectRaw('SUM(hits) as hits')
                ->groupBy('dimension_key')
                ->orderByDesc('hits')
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    [$source, $medium, $campaign] = array_pad(explode('||', (string) $row->dimension_key, 3), 3, '');

                    return [
                        'source' => $source,
                        'medium' => $medium !== '' ? $medium : '—',
                        'campaign' => $campaign !== '' ? $campaign : '—',
                        'hits' => (int) $row->hits,
                    ];
                })
                ->values()
                ->all();
        }

        $query = self::scopedEventsQuery($start, $end, $indiaFocus)
            ->whereNotNull('utm_source')
            ->where('utm_source', '!=', '');

        return $query
            ->select('utm_source', 'utm_medium', 'utm_campaign')
            ->selectRaw('COUNT(*) as hits')
            ->groupBy('utm_source', 'utm_medium', 'utm_campaign')
            ->orderByDesc('hits')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'source' => $row->utm_source,
                'medium' => $row->utm_medium ?: '—',
                'campaign' => $row->utm_campaign ?: '—',
                'hits' => (int) $row->hits,
            ])
            ->values()
            ->all();
    }

    private static function paidOrdersQuery(?Carbon $start, ?Carbon $end)
    {
        return DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->when($start && $end, fn ($query) => $query->whereBetween('paid_at', [$start, $end]));
    }

    /**
     * @return list<array{channel: string, channel_label: string, paid_orders: int, revenue: float}>
     */
    private static function paidByChannel(?Carbon $start, ?Carbon $end): array
    {
        $unknown = DonationAttributionService::CHANNEL_UNKNOWN;

        return self::paidOrdersQuery($start, $end)
            ->selectRaw('source_channel')
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('source_channel')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($row) use ($unknown) {
                $channel = filled($row->source_channel) ? (string) $row->source_channel : $unknown;

                return [
                    'channel' => $channel,
                    'channel_label' => DonationAttributionService::channelLabel($channel),
                    'paid_orders' => (int) $row->paid_orders,
                    'revenue' => (float) $row->revenue,
                ];
            })
            ->groupBy('channel')
            ->map(fn ($group) => [
                'channel' => $group->first()['channel'],
                'channel_label' => $group->first()['channel_label'],
                'paid_orders' => (int) $group->sum('paid_orders'),
                'revenue' => (float) $group->sum('revenue'),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * @return list<array{source: string, medium: string, campaign: string, content: string, paid_orders: int, revenue: float}>
     */
    private static function paidByUtm(?Carbon $start, ?Carbon $end): array
    {
        return self::paidOrdersQuery($start, $end)
            ->where(function ($query) {
                $query->whereNotNull('attr_source')
                    ->orWhere(function ($legacy) {
                        $legacy->whereNotNull('utm_source')->where('utm_source', '!=', '');
                    });
            })
            ->selectRaw('COALESCE(attr_source, utm_source) as report_source')
            ->selectRaw('COALESCE(attr_medium, utm_medium) as report_medium')
            ->selectRaw('COALESCE(attr_platform, \'\') as report_platform')
            ->addSelect('utm_campaign', 'utm_content')
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('report_source', 'report_medium', 'report_platform', 'utm_campaign', 'utm_content')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get()
            ->map(function ($row) {
                $platform = filled($row->report_platform) ? ' · '.AttributionTaxonomy::platformLabel($row->report_platform) : '';

                return [
                    'source' => AttributionTaxonomy::sourceLabel($row->report_source).$platform,
                    'medium' => $row->report_medium ?: '—',
                    'campaign' => $row->utm_campaign ?: '—',
                    'content' => $row->utm_content ?: '—',
                    'paid_orders' => (int) $row->paid_orders,
                    'revenue' => (float) $row->revenue,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{content: string, paid_orders: int, revenue: float}>
     */
    private static function paidByUtmContent(?Carbon $start, ?Carbon $end): array
    {
        return self::paidOrdersQuery($start, $end)
            ->whereNotNull('utm_content')
            ->where('utm_content', '!=', '')
            ->select('utm_content')
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('utm_content')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'content' => (string) $row->utm_content,
                'paid_orders' => (int) $row->paid_orders,
                'revenue' => (float) $row->revenue,
            ])
            ->values()
            ->all();
    }

    /**
     * Exact per-marketer revenue, keyed on the partner resolved from `sid`.
     *
     * @return list<array{partner_id: int, name: string, code: string, paid_orders: int, revenue: float}>
     */
    private static function paidByPartner(?Carbon $start, ?Carbon $end): array
    {
        $rows = self::paidOrdersQuery($start, $end)
            ->whereNotNull('partner_user_id')
            ->select('partner_user_id', 'partner_code')
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('partner_user_id', 'partner_code')
            ->orderByDesc('revenue')
            ->limit(25)
            ->get();

        $names = User::query()
            ->whereIn('id', $rows->pluck('partner_user_id')->unique()->all())
            ->pluck('name', 'id');

        return $rows
            ->map(fn ($row) => [
                'partner_id' => (int) $row->partner_user_id,
                'name' => (string) ($names[$row->partner_user_id] ?? 'Unknown'),
                'code' => (string) ($row->partner_code ?? '—'),
                'paid_orders' => (int) $row->paid_orders,
                'revenue' => (float) $row->revenue,
            ])
            ->values()
            ->all();
    }

    /**
     * Exact Meta campaign/adset/ad revenue captured from Meta's dynamic URL tokens.
     *
     * @return list<array{campaign_id: string, adset_id: string, ad_id: string, campaign: string, paid_orders: int, revenue: float}>
     */
    private static function paidByMetaAd(?Carbon $start, ?Carbon $end): array
    {
        return self::paidOrdersQuery($start, $end)
            ->whereNotNull('meta_campaign_id')
            ->select('meta_campaign_id', 'meta_adset_id', 'meta_ad_id')
            ->selectRaw('MAX(utm_campaign) as campaign_name')
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('meta_campaign_id', 'meta_adset_id', 'meta_ad_id')
            ->orderByDesc('revenue')
            ->limit(25)
            ->get()
            ->map(fn ($row) => [
                'campaign_id' => (string) $row->meta_campaign_id,
                'adset_id' => (string) ($row->meta_adset_id ?? '—'),
                'ad_id' => (string) ($row->meta_ad_id ?? '—'),
                'campaign' => (string) ($row->campaign_name ?: '—'),
                'paid_orders' => (int) $row->paid_orders,
                'revenue' => (float) $row->revenue,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{referrer: string, paid_orders: int, revenue: float}>
     */
    private static function paidByReferrer(?Carbon $start, ?Carbon $end): array
    {
        $rows = self::paidOrdersQuery($start, $end)
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->select('referrer')
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('referrer')
            ->get();

        return $rows
            ->groupBy(fn ($row) => self::normalizeReferrerHost((string) $row->referrer) ?: 'unknown')
            ->map(function ($group, string $host) {
                return [
                    'referrer' => $host,
                    'paid_orders' => (int) $group->sum('paid_orders'),
                    'revenue' => (float) $group->sum('revenue'),
                ];
            })
            ->sortByDesc('revenue')
            ->take(10)
            ->values()
            ->all();
    }

    private static function deviceBreakdown(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        if (self::rollupsCoverRange($start, $end)) {
            $scope = self::rollupScope($indiaFocus);
            $bounds = self::rollupDateBounds($start, $end);

            return AnalyticsDailyDimension::query()
                ->where('scope', $scope)
                ->where('dimension_type', AnalyticsDailyDimension::TYPE_DEVICE)
                ->tap(fn ($query) => self::constrainRollupDates($query, $bounds))
                ->select('dimension_key')
                ->selectRaw('SUM(visitors) as visitors')
                ->groupBy('dimension_key')
                ->orderByDesc('visitors')
                ->get()
                ->map(fn ($row) => [
                    'device' => ucfirst((string) ($row->dimension_key ?: 'unknown')),
                    'visitors' => (int) $row->visitors,
                ])
                ->values()
                ->all();
        }

        $rows = self::visitorEventsQuery($start, $end, $indiaFocus)
            ->whereNotNull('session_id')
            ->select('device_type')
            ->selectRaw('COUNT(DISTINCT session_id) as visitors')
            ->groupBy('device_type')
            ->orderByDesc('visitors')
            ->get();

        return $rows->map(fn ($row) => [
            'device' => ucfirst((string) ($row->device_type ?: 'unknown')),
            'visitors' => (int) $row->visitors,
        ])->values()->all();
    }

    private static function failedPayments(?Carbon $start, ?Carbon $end): array
    {
        $query = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::TYPE_DONATION_FAILED);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        $rows = $query
            ->select('cause_id')
            ->selectRaw('COUNT(*) as failures')
            ->selectRaw('SUM(amount) as lost_amount')
            ->groupBy('cause_id')
            ->orderByDesc('failures')
            ->limit(8)
            ->get();

        $causes = Cause::query()
            ->whereIn('id', $rows->pluck('cause_id')->filter())
            ->pluck('title', 'id');

        return $rows->map(fn ($row) => [
            'cause' => $causes[$row->cause_id] ?? 'Unknown',
            'failures' => (int) $row->failures,
            'lost_amount' => (float) $row->lost_amount,
        ])->values()->all();
    }

    private static function abandonedCheckouts(?Carbon $start, ?Carbon $end): array
    {
        // Order-table only — avoid scanning analytics_events for checkout/paid joins.
        $abandonedOrdersQuery = DonationOrder::query()
            ->whereIn('status', [
                DonationOrder::STATUS_PENDING,
                DonationOrder::STATUS_FAILED,
            ])
            ->whereNull('paid_at')
            ->whereNotNull('provider_order_id');

        if ($start && $end) {
            $abandonedOrdersQuery->whereBetween('created_at', [$start, $end]);
        }

        $count = (int) (clone $abandonedOrdersQuery)->count();

        if ($count === 0) {
            return [
                'count' => 0,
                'amount' => 0.0,
                'orders' => [],
            ];
        }

        $amount = (float) (clone $abandonedOrdersQuery)->sum('total_amount');

        $orders = (clone $abandonedOrdersQuery)
            ->with('items.causeModel')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return [
            'count' => $count,
            'amount' => $amount,
            'orders' => $orders->map(fn (DonationOrder $order) => [
                'donor_name' => $order->donor_name,
                'cause' => $order->items->first()?->causeModel?->title ?? '—',
                'amount' => (float) $order->total_amount,
                'created_at' => $order->created_at?->format('d M Y, h:i A'),
                'detail_url' => route('admin.donations.show', $order),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private static function subscriptionMetrics(?Carbon $start, ?Carbon $end): array
    {
        $createdQuery = DonationSubscription::query();

        if ($start && $end) {
            $createdQuery->whereBetween('created_at', [$start, $end]);
        }

        $checkoutQuery = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::TYPE_SUBSCRIPTION_CHECKOUT_STARTED);

        if ($start && $end) {
            $checkoutQuery->whereBetween('created_at', [$start, $end]);
        }

        $recurringRevenueQuery = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->where('is_recurring', true);

        if ($start && $end) {
            $recurringRevenueQuery->whereBetween('paid_at', [$start, $end]);
        }

        $cancelledQuery = DonationSubscription::query()
            ->where('status', DonationSubscription::STATUS_CANCELLED);

        if ($start && $end) {
            $cancelledQuery->whereBetween('cancelled_at', [$start, $end]);
        }

        return [
            'checkouts_started' => (int) $checkoutQuery->count(),
            'subscriptions_created' => (int) (clone $createdQuery)->count(),
            'live_subscriptions' => DonationSubscription::query()->live()->count(),
            'cancelled_in_period' => (int) $cancelledQuery->count(),
            'recurring_revenue' => (float) $recurringRevenueQuery->sum('total_amount'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function donorMetrics(?Carbon $start, ?Carbon $end): array
    {
        $periodDonorsQuery = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->whereNotNull('donor_id')
            ->when($start && $end, fn ($query) => $query->whereBetween('paid_at', [$start, $end]));

        $donorsInPeriod = (int) (clone $periodDonorsQuery)->distinct()->count('donor_id');

        if ($donorsInPeriod === 0) {
            return [
                'donors_in_period' => 0,
                'new_donors' => 0,
                'repeat_donors' => 0,
            ];
        }

        $firstPaidSubquery = DonationOrder::query()
            ->select('donor_id')
            ->selectRaw('MIN(paid_at) as first_paid_at')
            ->where('status', DonationOrder::STATUS_PAID)
            ->whereNotNull('donor_id')
            ->groupBy('donor_id');

        $newDonorsQuery = DB::query()
            ->fromSub($firstPaidSubquery, 'first_paid')
            ->whereIn('donor_id', (clone $periodDonorsQuery)->select('donor_id')->distinct());

        if ($start && $end) {
            $newDonorsQuery->whereBetween('first_paid_at', [$start, $end]);
        }

        $newDonors = (int) $newDonorsQuery->count();

        $repeatDonors = (int) DB::query()
            ->fromSub(
                DonationOrder::query()
                    ->select('donor_id')
                    ->where('status', DonationOrder::STATUS_PAID)
                    ->whereNotNull('donor_id')
                    ->whereIn('donor_id', (clone $periodDonorsQuery)->select('donor_id')->distinct())
                    ->groupBy('donor_id')
                    ->havingRaw('COUNT(*) > 1'),
                'repeat_donors'
            )
            ->count();

        return [
            'donors_in_period' => $donorsInPeriod,
            'new_donors' => $newDonors,
            'repeat_donors' => $repeatDonors,
        ];
    }

    private static function hourlyActivity(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        if (self::rollupsCoverRange($start, $end)) {
            $scope = self::rollupScope($indiaFocus);
            $bounds = self::rollupDateBounds($start, $end);

            $rows = AnalyticsDailyDimension::query()
                ->where('scope', $scope)
                ->where('dimension_type', AnalyticsDailyDimension::TYPE_HOUR)
                ->tap(fn ($query) => self::constrainRollupDates($query, $bounds))
                ->select('dimension_key')
                ->selectRaw('SUM(hits) as visits')
                ->selectRaw('SUM(visitors) as paid')
                ->groupBy('dimension_key')
                ->get()
                ->keyBy(fn ($row) => (int) $row->dimension_key);
        } else {
            $driver = DB::connection()->getDriverName();
            $hourExpression = $driver === 'sqlite'
                ? "CAST(strftime('%H', created_at) AS INTEGER)"
                : 'HOUR(created_at)';

            $rows = self::scopedEventsQuery($start, $end, $indiaFocus)
                ->selectRaw("{$hourExpression} as hour")
                ->selectRaw('SUM(CASE WHEN event_type IN (?, ?) THEN 1 ELSE 0 END) as visits', [
                    AnalyticsEvent::TYPE_VISIT_HOME,
                    AnalyticsEvent::TYPE_VISIT_CAUSE,
                ])
                ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as paid', [AnalyticsEvent::TYPE_DONATION_PAID])
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->keyBy('hour');
        }

        $points = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $row = $rows->get($hour);

            $points[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'visits' => (int) ($row->visits ?? 0),
                'paid' => (int) ($row->paid ?? 0),
            ];
        }

        return $points;
    }

    public static function normalizeReferrerHost(?string $referrer): ?string
    {
        if (! is_string($referrer) || trim($referrer) === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return Str::lower(trim($referrer));
        }

        $host = Str::lower($host);

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        if (in_array($host, ['m.facebook.com', 'l.facebook.com', 'lm.facebook.com', 'web.facebook.com'], true)) {
            return 'facebook.com';
        }

        if (str_ends_with($host, '.facebook.com')) {
            return 'facebook.com';
        }

        if (in_array($host, ['l.instagram.com', 'www.instagram.com'], true) || str_ends_with($host, '.instagram.com')) {
            return 'instagram.com';
        }

        return $host;
    }

    /**
     * SQL expression that extracts a lowercase host from a referrer URL.
     */
    private static function referrerHostSqlExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $withoutScheme = "REPLACE(REPLACE(referrer, 'https://', ''), 'http://', '')";

            return "LOWER(REPLACE(
                CASE
                    WHEN INSTR({$withoutScheme}, '/') > 0
                    THEN SUBSTR({$withoutScheme}, 1, INSTR({$withoutScheme}, '/') - 1)
                    ELSE {$withoutScheme}
                END,
                'www.',
                ''
            ))";
        }

        // MySQL/MariaDB: strip scheme, take host only, drop leading www.
        return "LOWER(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '://', -1), '/', 1), '?', 1), 'www.', ''))";
    }

    private static function locationBreakdown(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        return [
            'countries' => self::topCountries($start, $end, $indiaFocus),
            'regions' => self::topRegions($start, $end, $indiaFocus),
            'cities' => self::topCities($start, $end, $indiaFocus),
        ];
    }

    private static function visitorEventsQuery(?Carbon $start, ?Carbon $end, bool $indiaFocus = false)
    {
        $query = self::scopedEventsQuery($start, $end, $indiaFocus)
            ->whereIn('event_type', [
                AnalyticsEvent::TYPE_VISIT_HOME,
                AnalyticsEvent::TYPE_VISIT_CAUSE,
            ])
            ->whereNotNull('session_id');

        return $query;
    }

    private static function distinctCountries(?Carbon $start, ?Carbon $end, bool $indiaFocus): int
    {
        return (int) self::visitorEventsQuery($start, $end, $indiaFocus)
            ->whereNotNull('country_code')
            ->distinct()
            ->count('country_code');
    }

    private static function topCountries(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        if (self::rollupsCoverRange($start, $end)) {
            $scope = self::rollupScope($indiaFocus);
            $bounds = self::rollupDateBounds($start, $end);

            return AnalyticsDailyDimension::query()
                ->where('scope', $scope)
                ->where('dimension_type', AnalyticsDailyDimension::TYPE_COUNTRY)
                ->tap(fn ($query) => self::constrainRollupDates($query, $bounds))
                ->select('dimension_key')
                ->selectRaw('SUM(visitors) as visitors')
                ->selectRaw('SUM(hits) as pageviews')
                ->groupBy('dimension_key')
                ->orderByDesc('visitors')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    [$code, $name] = array_pad(explode('||', (string) $row->dimension_key, 2), 2, '');

                    return [
                        'label' => $name !== '' ? Str::title($name) : Str::upper($code),
                        'code' => $code !== '' ? Str::upper($code) : null,
                        'visitors' => (int) $row->visitors,
                        'pageviews' => (int) $row->pageviews,
                    ];
                })
                ->values()
                ->all();
        }

        return self::visitorEventsQuery($start, $end, $indiaFocus)
            ->whereNotNull('country_name')
            ->where('country_name', '!=', '')
            ->select('country_code', 'country_name')
            ->selectRaw('COUNT(DISTINCT session_id) as visitors')
            ->selectRaw('COUNT(*) as pageviews')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('visitors')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->country_name,
                'code' => $row->country_code,
                'visitors' => (int) $row->visitors,
                'pageviews' => (int) $row->pageviews,
            ])
            ->values()
            ->all();
    }

    private static function topRegions(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        if (self::rollupsCoverRange($start, $end)) {
            $scope = self::rollupScope($indiaFocus);
            $bounds = self::rollupDateBounds($start, $end);

            return AnalyticsDailyDimension::query()
                ->where('scope', $scope)
                ->where('dimension_type', AnalyticsDailyDimension::TYPE_REGION)
                ->tap(fn ($query) => self::constrainRollupDates($query, $bounds))
                ->select('dimension_key')
                ->selectRaw('SUM(visitors) as visitors')
                ->selectRaw('SUM(hits) as pageviews')
                ->groupBy('dimension_key')
                ->orderByDesc('visitors')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    [$region, $country] = array_pad(explode('||', (string) $row->dimension_key, 2), 2, '');
                    $region = Str::title($region);
                    $country = $country !== '' ? Str::title($country) : '';

                    return [
                        'label' => trim($region.($country !== '' ? ', '.$country : '')),
                        'visitors' => (int) $row->visitors,
                        'pageviews' => (int) $row->pageviews,
                    ];
                })
                ->values()
                ->all();
        }

        return self::visitorEventsQuery($start, $end, $indiaFocus)
            ->whereNotNull('region_name')
            ->where('region_name', '!=', '')
            ->select('country_name', 'region_name')
            ->selectRaw('COUNT(DISTINCT session_id) as visitors')
            ->selectRaw('COUNT(*) as pageviews')
            ->groupBy('country_name', 'region_name')
            ->orderByDesc('visitors')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'label' => trim($row->region_name.($row->country_name ? ', '.$row->country_name : '')),
                'visitors' => (int) $row->visitors,
                'pageviews' => (int) $row->pageviews,
            ])
            ->values()
            ->all();
    }

    private static function topCities(?Carbon $start, ?Carbon $end, bool $indiaFocus): array
    {
        if (self::rollupsCoverRange($start, $end)) {
            $scope = self::rollupScope($indiaFocus);
            $bounds = self::rollupDateBounds($start, $end);

            return AnalyticsDailyDimension::query()
                ->where('scope', $scope)
                ->where('dimension_type', AnalyticsDailyDimension::TYPE_CITY)
                ->tap(fn ($query) => self::constrainRollupDates($query, $bounds))
                ->select('dimension_key')
                ->selectRaw('SUM(visitors) as visitors')
                ->selectRaw('SUM(hits) as pageviews')
                ->groupBy('dimension_key')
                ->orderByDesc('visitors')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    [$city, $region, $country] = array_pad(explode('||', (string) $row->dimension_key, 3), 3, '');
                    $suffix = collect([
                        $region !== '' ? Str::title($region) : null,
                        $country !== '' ? Str::title($country) : null,
                    ])->filter()->implode(', ');

                    return [
                        'label' => trim(Str::title($city).($suffix !== '' ? ', '.$suffix : '')),
                        'visitors' => (int) $row->visitors,
                        'pageviews' => (int) $row->pageviews,
                    ];
                })
                ->values()
                ->all();
        }

        return self::visitorEventsQuery($start, $end, $indiaFocus)
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->select('city', 'region_name', 'country_name')
            ->selectRaw('COUNT(DISTINCT session_id) as visitors')
            ->selectRaw('COUNT(*) as pageviews')
            ->groupBy('city', 'region_name', 'country_name')
            ->orderByDesc('visitors')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $suffix = collect([$row->region_name, $row->country_name])->filter()->implode(', ');

                return [
                    'label' => trim($row->city.($suffix !== '' ? ', '.$suffix : '')),
                    'visitors' => (int) $row->visitors,
                    'pageviews' => (int) $row->pageviews,
                ];
            })
            ->values()
            ->all();
    }
}
