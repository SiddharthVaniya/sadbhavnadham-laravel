<?php

namespace App\Support;

use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\LinkTrackingSummary;
use App\Models\LinkTrackingVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketerPerformanceData
{
    public const DURATION_OPTIONS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_week' => 'This week',
        'last_week' => 'Previous week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'all' => 'All time',
        'custom' => 'Custom range',
    ];

    /** @var array<string, string> */
    private const DONATION_SORT_COLUMNS = [
        'campaign' => 'utm_campaign',
        'medium' => 'utm_medium',
        'ad' => 'utm_content',
        'pincode' => 'pincode',
        'city' => 'city',
        'state' => 'state',
        'ip_address' => 'ip_address',
        'ip_location' => 'ip_city',
        'amount' => 'total_amount',
        'time' => 'paid_at',
        'device' => 'device_type',
    ];

    /** @var array<string, string> */
    private const VISIT_SORT_COLUMNS = [
        'id' => 'id',
        'created_at' => 'created_at',
        'converted' => 'converted',
        'converted_amount' => 'converted_amount',
        'converted_at' => 'converted_at',
        'ip_address' => 'ip_address',
        'device_type' => 'device_type',
        'is_unique' => 'is_unique',
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'utm_campaign' => 'utm_campaign',
        'utm_content' => 'utm_content',
        'sid' => 'sid',
        'utm_term' => 'utm_term',
        'utm_id' => 'utm_id',
        'page_path' => 'page_path',
        'landing_url' => 'landing_url',
        'referrer' => 'referrer',
        'fbclid' => 'fbclid',
        'amt' => 'amt',
        'ptype' => 'ptype',
        'user_agent' => 'user_agent',
        'visitor_id' => 'visitor_id',
    ];

    private const VISIT_EXPORT_LIMIT = 5000;

    /**
     * @return array<string, mixed>
     */
    public static function dashboard(User $user, Request $request): array
    {
        $duration = (string) $request->input('duration', '30d');

        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = '30d';
        }

        $range = self::resolveMarketerRange($request, $duration);
        $filters = self::donationFilters($request);
        $orders = self::attributedPaidOrders($user, $range);
        self::applyDonationDeviceFilter($orders, $filters);

        $donations = (clone $orders)->count();
        $revenue = round((float) (clone $orders)->sum('total_amount'), 2);

        return [
            'duration' => $duration,
            'durationOptions' => self::DURATION_OPTIONS,
            'durationLabel' => $range['label'],
            'filters' => $filters,
            'filterOptions' => [
                'device_type' => self::distinctDonationDevices($user),
            ],
            'profile' => [
                'code' => StaffReferral::normalize($user->referral_code),
            ],
            'summary' => [
                'donations' => $donations,
                'revenue' => $revenue,
                'average_donation' => $donations > 0 ? round($revenue / $donations, 2) : 0,
            ],
            'monthlyBudget' => MarketerMonthlyBudgetService::forUserMonth($user),
            'target' => self::donationTargetProgress($user),
            'dailyTrend' => self::donationDailyCollectionTrend($user, $range, $duration, $filters),
            'campaignRevenueTrend' => self::donationCampaignRevenueTrend($user, $range, $duration, $filters),
            'topCampaigns' => self::donationTopCampaignBreakdown($user, $range, $filters),
            'donations' => self::recentAttributedDonations($user, $range, 12, $filters),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function donations(User $user, Request $request): array
    {
        $duration = (string) $request->input('duration', '30d');

        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = '30d';
        }

        $range = self::resolveMarketerRange($request, $duration);
        $filters = self::donationListFilters($request);
        [$sort, $dir, $sortColumn] = self::resolveDonationSort($request);
        $orders = self::attributedPaidOrders($user, $range);
        self::applyDonationListFilters($orders, $filters);

        $donations = (clone $orders)->count();
        $revenue = round((float) (clone $orders)->sum('total_amount'), 2);

        $paginator = (clone $orders)
            ->with('items.causeModel')
            ->orderBy($sortColumn, $dir)
            ->orderByDesc('id')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return [
            'duration' => $duration,
            'durationOptions' => self::DURATION_OPTIONS,
            'durationLabel' => $range['label'],
            'filters' => $filters,
            'filterOptions' => self::donationFilterOptions($user),
            'sort' => $sort,
            'dir' => $dir,
            'profile' => [
                'code' => StaffReferral::normalize($user->referral_code),
            ],
            'summary' => [
                'donations' => $donations,
                'revenue' => $revenue,
                'average_donation' => $donations > 0 ? round($revenue / $donations, 2) : 0,
            ],
            'donations' => AdminInertiaResources::paginated(
                $paginator,
                fn (DonationOrder $order) => self::marketerDonationRow($order),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function campaigns(User $user, Request $request): array
    {
        $payload = self::context($user, $request);
        $range = $payload['range'];
        $filters = $payload['filters'];
        unset($payload['range']);

        return [
            ...$payload,
            'target' => self::targetProgress($user),
            'campaigns' => self::campaignRows($user, $range, $filters),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function visits(User $user, Request $request): array
    {
        $payload = self::context($user, $request);
        $range = $payload['range'];
        $filters = $payload['filters'];
        unset($payload['range']);
        [$sort, $dir, $sortColumn] = self::resolveVisitSort($request);
        $clickSummaryFilters = $filters;
        $clickSummaryFilters['result'] = '';
        $clickScoped = self::scopedVisits($user, $range, $clickSummaryFilters);
        $trackedClicks = (clone $clickScoped)->count();
        $clicksOnly = (clone $clickScoped)->where('converted', false)->count();
        $donated = (clone self::attributedPaidOrders($user, $range))->count();
        $query = self::scopedVisits($user, $range, $filters);
        $paginator = $query
            ->orderBy($sortColumn, $dir)
            ->when($sortColumn !== 'id', fn (Builder $builder) => $builder->orderByDesc('id'))
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return [
            ...$payload,
            'sort' => $sort,
            'dir' => $dir,
            'target' => self::targetProgress($user),
            'resultBreakdown' => [
                [
                    'name' => 'Tracked clicks',
                    'count' => $trackedClicks,
                    'percentage' => $trackedClicks > 0 ? 100.0 : 0.0,
                ],
                [
                    'name' => 'Donated',
                    'count' => $donated,
                    'percentage' => $trackedClicks > 0
                        ? round(($donated / $trackedClicks) * 100, 1)
                        : 0.0,
                ],
                [
                    'name' => 'Clicks only',
                    'count' => $clicksOnly,
                    'percentage' => $trackedClicks > 0
                        ? round(($clicksOnly / $trackedClicks) * 100, 1)
                        : 0.0,
                ],
            ],
            'visits' => AdminInertiaResources::paginated(
                $paginator,
                fn (LinkTrackingVisit $visit) => self::visitRow($visit),
            ),
        ];
    }

    /**
     * @return array{title: string, periodLabel: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    public static function exportDashboard(User $user, Request $request): array
    {
        $duration = (string) $request->input('duration', '30d');

        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = '30d';
        }

        $range = self::resolveMarketerRange($request, $duration);
        $filters = self::donationFilters($request);
        [$sort, $dir, $sortColumn] = self::resolveDonationSort($request);
        $orders = self::attributedPaidOrders($user, $range);
        self::applyDonationDeviceFilter($orders, $filters);

        $rows = $orders
            ->with('items.causeModel')
            ->orderBy($sortColumn, $dir)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DonationOrder $order) => self::marketerDonationExportRow($order))
            ->values()
            ->all();

        return [
            'title' => 'Marketer Dashboard Donations',
            'periodLabel' => $range['label'],
            'headers' => self::donationExportHeaders(),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, periodLabel: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    public static function exportDonations(User $user, Request $request): array
    {
        $duration = (string) $request->input('duration', '30d');

        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = '30d';
        }

        $range = self::resolveMarketerRange($request, $duration);
        $filters = self::donationListFilters($request);
        [$sort, $dir, $sortColumn] = self::resolveDonationSort($request);
        $orders = self::attributedPaidOrders($user, $range);
        self::applyDonationListFilters($orders, $filters);

        $rows = $orders
            ->with('items.causeModel')
            ->orderBy($sortColumn, $dir)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DonationOrder $order) => self::marketerDonationExportRow($order))
            ->values()
            ->all();

        return [
            'title' => 'Marketer Donations',
            'periodLabel' => $range['label'],
            'headers' => self::donationExportHeaders(),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, periodLabel: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    public static function exportCampaigns(User $user, Request $request): array
    {
        $payload = self::context($user, $request);
        $rows = collect(self::campaignRows($user, $payload['range'], $payload['filters']))
            ->map(fn (array $row) => [
                'Campaign' => $row['utm_campaign'] ?: 'Untitled',
                'Clicks' => (int) $row['clicks'],
                'Unique' => (int) $row['unique_visitors'],
                'Donations' => (int) $row['conversions'],
                'Revenue' => (float) $row['revenue'],
                'Conversion rate' => (float) $row['conversion_rate'],
                'Last click' => $row['last_click_at'] ?? '',
            ])
            ->values()
            ->all();

        return [
            'title' => 'Marketer Campaigns',
            'periodLabel' => $payload['durationLabel'],
            'headers' => ['Campaign', 'Clicks', 'Unique', 'Donations', 'Revenue', 'Conversion rate', 'Last click'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, periodLabel: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    public static function exportVisits(User $user, Request $request): array
    {
        $payload = self::context($user, $request);
        [$sort, $dir, $sortColumn] = self::resolveVisitSort($request);
        $rows = self::scopedVisits($user, $payload['range'], $payload['filters'])
            ->orderBy($sortColumn, $dir)
            ->when($sortColumn !== 'id', fn (Builder $builder) => $builder->orderByDesc('id'))
            ->limit(self::VISIT_EXPORT_LIMIT)
            ->get()
            ->map(fn (LinkTrackingVisit $visit) => self::visitExportRow($visit))
            ->values()
            ->all();

        return [
            'title' => 'Marketer Visits',
            'periodLabel' => $payload['durationLabel'],
            'headers' => [
                'When',
                'Converted',
                'Donation',
                'Converted at',
                'IP',
                'Device',
                'Unique',
                'Source',
                'Medium',
                'Campaign',
                'Ad',
                'Referral (sid)',
                'Ad set ID',
                'Ad ID',
                'UTM ID',
                'Page',
                'Landing URL',
                'Referrer',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *     duration: string,
     *     durationOptions: array<string, string>,
     *     durationLabel: string,
     *     profile: array{name: string, email: string, code: ?string, share_url: string, meta_ad_parameters: string},
     *     filters: array<string, string>,
     *     filterOptions: array<string, list<string>>,
     *     range: array{start: ?Carbon, end: ?Carbon, label: string}
     * }
     */
    private static function context(User $user, Request $request, bool $includeVisitFilters = true): array
    {
        $duration = (string) $request->input('duration', '30d');

        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = '30d';
        }

        $range = self::resolveMarketerRange($request, $duration);
        $code = StaffReferral::normalize($user->referral_code);
        $filters = $includeVisitFilters
            ? self::visitFilters($request)
            : self::donationFilters($request);

        return [
            'duration' => $duration,
            'durationOptions' => self::DURATION_OPTIONS,
            'durationLabel' => $range['label'],
            'filters' => $filters,
            'filterOptions' => $includeVisitFilters
                ? self::visitFilterOptions($user)
                : [],
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'code' => $code,
                'share_url' => $code
                    ? StaffReferral::trackedShareUrl(DonationPublicFrontend::homeUrl(), $code)
                    : DonationPublicFrontend::homeUrl(),
                'meta_ad_parameters' => StaffReferral::metaAdParameters($code, $user->name),
            ],
            'range' => $range,
        ];
    }

    /**
     * @return array{start: ?Carbon, end: ?Carbon, label: string}
     */
    private static function resolveMarketerRange(Request $request, string $duration): array
    {
        if (in_array($duration, PeriodRange::CALENDAR_KEYS, true)) {
            return AdminReportsData::rangeForDuration($duration);
        }

            return AdminAnalyticsData::resolveRange($request, $duration);
        }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, string>  $filters
     */
    public static function scopedVisits(User $user, array $range, array $filters = []): Builder
    {
        $query = self::attributedVisits($user);
        self::applyVisitFilters($query, $filters);

        $result = $filters['result'] ?? '';

        if ($result === 'donated') {
            self::applyConvertedAtRange($query, $range);

            return $query;
        }

        if ($range['start'] !== null) {
            $query->where('created_at', '>=', $range['start']);
        }

        if ($range['end'] !== null) {
            $query->where('created_at', '<=', $range['end']);
        }

        return $query;
    }

    /**
     * Period filter for conversions: prefer payment time (converted_at).
     * Legacy rows with null converted_at fall back to created_at.
     *
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     */
    private static function applyConvertedAtRange(Builder $query, array $range): void
    {
        if ($range['start'] !== null) {
            $start = $range['start'];
            $query->where(function (Builder $builder) use ($start): void {
                $builder->where(function (Builder $converted) use ($start): void {
                    $converted->whereNotNull('converted_at')
                        ->where('converted_at', '>=', $start);
                })->orWhere(function (Builder $legacy) use ($start): void {
                    $legacy->whereNull('converted_at')
                        ->where('created_at', '>=', $start);
                });
            });
        }

        if ($range['end'] !== null) {
            $end = $range['end'];
            $query->where(function (Builder $builder) use ($end): void {
                $builder->where(function (Builder $converted) use ($end): void {
                    $converted->whereNotNull('converted_at')
                        ->where('converted_at', '<=', $end);
                })->orWhere(function (Builder $legacy) use ($end): void {
                    $legacy->whereNull('converted_at')
                        ->where('created_at', '<=', $end);
                });
            });
        }
    }

    /**
     * Paid donations attributed to this marketer, scoped by paid_at.
     *
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     */
    private static function attributedPaidOrders(User $user, array $range): Builder
    {
        $query = DonationOrder::query()->where('status', DonationOrder::STATUS_PAID);
        AdminStaffReferralsData::applyPartnerAttributionFilter($query, $user);

        if ($range['start'] !== null) {
            $query->where('paid_at', '>=', $range['start']);
        }

        if ($range['end'] !== null) {
            $query->where('paid_at', '<=', $range['end']);
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private static function donationFilters(Request $request): array
    {
        return [
            'from_date' => trim((string) $request->input('from_date', '')),
            'to_date' => trim((string) $request->input('to_date', '')),
            'device_type' => trim((string) $request->input('device_type', '')),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function donationListFilters(Request $request): array
    {
        $filters = self::donationFilters($request);

        foreach (['utm_campaign', 'utm_medium', 'utm_content', 'cause', 'title', 'city', 'state'] as $key) {
            $filters[$key] = trim((string) $request->input($key, ''));
        }

        return $filters;
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function applyDonationDeviceFilter(Builder $query, array $filters): void
    {
        $deviceType = $filters['device_type'] ?? '';

        if ($deviceType === 'unknown') {
            $query->where(function (Builder $deviceQuery): void {
                $deviceQuery->whereNull('device_type')
                    ->orWhere('device_type', '')
                    ->orWhere('device_type', 'unknown');
            });
        } elseif ($deviceType !== '') {
            $query->where('device_type', $deviceType);
        }
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function applyDonationListFilters(Builder $query, array $filters): void
    {
        foreach (['utm_campaign', 'utm_medium', 'utm_content', 'city', 'state'] as $column) {
            $value = $filters[$column] ?? '';
            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        self::applyDonationDeviceFilter($query, $filters);

        $title = $filters['title'] ?? '';
        if ($title !== '') {
            $query->whereHas('items', fn (Builder $items) => $items->where('title', $title));
        }

        $cause = $filters['cause'] ?? '';
        if ($cause !== '') {
            $query->whereHas('items', function (Builder $items) use ($cause): void {
                $items->where('cause', $cause)
                    ->orWhereHas('causeModel', fn (Builder $causeQuery) => $causeQuery->where('title', $cause));
            });
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function resolveDonationSort(Request $request): array
    {
        $sort = (string) $request->input('sort', 'time');
        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! array_key_exists($sort, self::DONATION_SORT_COLUMNS)) {
            $sort = 'time';
            $dir = 'desc';
        }

        return [$sort, $dir, self::DONATION_SORT_COLUMNS[$sort]];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function resolveVisitSort(Request $request): array
    {
        $sort = (string) $request->input('sort', 'id');
        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! array_key_exists($sort, self::VISIT_SORT_COLUMNS)) {
            $sort = 'id';
            $dir = 'desc';
        }

        return [$sort, $dir, self::VISIT_SORT_COLUMNS[$sort]];
    }

    /**
     * @return array{
     *     goal: ?int,
     *     achieved: int,
     *     remaining: ?int,
     *     achieved_percent: ?float,
     *     remaining_percent: ?float,
     *     period: 'this_month'|'lifetime'
     * }
     */
    private static function donationTargetProgress(User $user): array
    {
        $monthly = MarketerMonthlyBudgetService::forUserMonth($user);
        $useMonthly = $monthly['target_amount'] !== null && (int) $monthly['target_amount'] > 0;

        if ($useMonthly) {
            $goal = (int) $monthly['target_amount'];
            $achieved = (int) round((float) (self::attributedPaidOrders($user, [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfDay(),
            ])->sum('total_amount') ?? 0));
            $period = 'this_month';
        } else {
            $goal = $user->donation_target !== null ? (int) $user->donation_target : null;
            $achieved = (int) round((float) (self::attributedPaidOrders($user, [
                'start' => null,
                'end' => null,
            ])->sum('total_amount') ?? 0));
            $period = 'lifetime';
        }

        if ($goal === null || $goal <= 0) {
        return [
                'goal' => null,
                'achieved' => $achieved,
                'remaining' => null,
                'achieved_percent' => null,
                'remaining_percent' => null,
                'period' => $period,
            ];
        }

        $remaining = max(0, $goal - $achieved);
        $achievedPercent = min(100, round(($achieved / $goal) * 100, 1));

        return [
            'goal' => $goal,
            'achieved' => $achieved,
            'remaining' => $remaining,
            'achieved_percent' => $achievedPercent,
            'remaining_percent' => round(100 - $achievedPercent, 1),
            'period' => $period,
        ];
    }

    /**
     * @return list<array{key: string, label: string, donations: int, amount: float}>
     */
    private static function donationMonthlyTrend(User $user): array
    {
        $start = now()->subMonths(11)->startOfMonth();
        $query = self::attributedPaidOrders($user, [
            'start' => $start,
            'end' => now()->endOfDay(),
        ]);

        if (DB::connection()->getDriverName() === 'sqlite') {
            $query->select(
                DB::raw("CAST(strftime('%Y', paid_at) AS INTEGER) as year"),
                DB::raw("CAST(strftime('%m', paid_at) AS INTEGER) as month"),
                DB::raw('COUNT(*) as donations'),
                DB::raw('SUM(total_amount) as amount')
            );
        } else {
            $query->select(
                DB::raw('YEAR(paid_at) as year'),
                DB::raw('MONTH(paid_at) as month'),
                DB::raw('COUNT(*) as donations'),
                DB::raw('SUM(total_amount) as amount')
            );
        }

        $rows = $query
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn ($row) => sprintf('%04d-%02d', $row->year, $row->month));

        $months = collect();
        $cursor = $start->copy();

        while ($cursor <= now()->startOfMonth()) {
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);

            $months->push([
                'key' => $key,
                'label' => $cursor->format('M y'),
                'year' => (int) $cursor->year,
                'month' => (int) $cursor->month,
                'donations' => (int) ($row->donations ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ]);

            $cursor->addMonth();
        }

        return $months->values()->all();
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     * @param  array<string, string>  $filters
     * @return array{granularity: 'hour'|'day', points: list<array{key: string, label: string, donations: int, amount: float}>}
     */
    private static function donationDailyCollectionTrend(User $user, array $range, string $duration = '30d', array $filters = []): array
    {
        if (self::isHourlyTrendRange($duration, $range)) {
            return self::donationDailyCollectionTrendHourly($user, $range, $filters);
        }

        return self::donationDailyCollectionTrendDaily($user, $range, $filters);
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     * @param  array<string, string>  $filters
     * @return array{granularity: 'hour', points: list<array{key: string, label: string, donations: int, amount: float}>}
     */
    private static function donationDailyCollectionTrendHourly(User $user, array $range, array $filters = []): array
    {
        $ordersQuery = self::attributedPaidOrders($user, $range);
        self::applyDonationDeviceFilter($ordersQuery, $filters);
        $orders = $ordersQuery
            ->whereNotNull('paid_at')
            ->get(['paid_at', 'total_amount']);

        $timezone = config('app.timezone');
        $buckets = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $buckets[(string) $hour] = [
                'donations' => 0,
                'amount' => 0.0,
            ];
        }

        foreach ($orders as $order) {
            $paidAt = $order->paid_at?->timezone($timezone);

            if ($paidAt === null) {
                continue;
            }

            $key = (string) (int) $paidAt->format('G');
            $buckets[$key]['donations']++;
            $buckets[$key]['amount'] += (float) $order->total_amount;
        }

        $points = collect(range(0, 23))
            ->map(fn (int $hour) => [
                'key' => (string) $hour,
                'label' => self::hourAxisLabel($hour),
                'donations' => $buckets[(string) $hour]['donations'],
                'amount' => round($buckets[(string) $hour]['amount'], 2),
            ])
            ->values()
            ->all();

        return [
            'granularity' => 'hour',
            'points' => $points,
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     * @param  array<string, string>  $filters
     * @return array{granularity: 'day', points: list<array{key: string, label: string, donations: int, amount: float}>}
     */
    private static function donationDailyCollectionTrendDaily(User $user, array $range, array $filters = []): array
    {
        $timezone = config('app.timezone');
        $end = ($range['end'] ?? now())->copy()->timezone($timezone)->endOfDay();
        $start = $range['start']?->copy()->timezone($timezone)->startOfDay();

        if ($start === null) {
            $firstPaidQuery = self::attributedPaidOrders($user, [
                'start' => null,
            'end' => $end,
            ]);
            self::applyDonationDeviceFilter($firstPaidQuery, $filters);
            $firstPaidAt = $firstPaidQuery->whereNotNull('paid_at')->min('paid_at');

            $start = $firstPaidAt !== null
                ? Carbon::parse((string) $firstPaidAt, $timezone)->startOfDay()
                : now($timezone)->subDays(29)->startOfDay();
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        if ($start->diffInDays($end) > 90) {
            $start = $end->copy()->subDays(89)->startOfDay();
        }

        $query = self::attributedPaidOrders($user, [
            'start' => $start,
            'end' => $end,
        ]);
        self::applyDonationDeviceFilter($query, $filters);
        $query->whereNotNull('paid_at');

        $rows = $query
            ->selectRaw('DATE(paid_at) as day')
            ->selectRaw('COUNT(*) as donations')
            ->selectRaw('SUM(total_amount) as amount')
            ->groupByRaw('DATE(paid_at)')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $points = [];
        $cursor = $start->copy()->startOfDay();

        while ($cursor->lte($end->copy()->startOfDay())) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);

            $points[] = [
                'key' => $key,
                'label' => $cursor->format('d M'),
                'donations' => (int) ($row->donations ?? 0),
                'amount' => round((float) ($row->amount ?? 0), 2),
            ];

            $cursor->addDay();
        }

        return [
            'granularity' => 'day',
            'points' => $points,
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     * @param  array<string, string>  $filters
     * @return array{granularity: 'hour'|'day', labels: list<array{key: string, label: string}>, series: list<array{name: string, points: list<array{revenue: float}>}>}
     */
    private static function donationCampaignRevenueTrend(User $user, array $range, string $duration = '30d', array $filters = []): array
    {
        if (self::isHourlyTrendRange($duration, $range)) {
            return self::donationCampaignRevenueTrendHourly($user, $range, $filters);
        }

        return self::donationCampaignRevenueTrendDaily($user, $range, $filters);
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     * @param  array<string, string>  $filters
     * @return array{granularity: 'hour', labels: list<array{key: string, label: string}>, series: list<array{name: string, points: list<array{revenue: float}>}>}
     */
    private static function donationCampaignRevenueTrendHourly(User $user, array $range, array $filters = []): array
    {
        $ordersQuery = self::attributedPaidOrders($user, $range);
        self::applyDonationDeviceFilter($ordersQuery, $filters);
        $orders = $ordersQuery
            ->whereNotNull('paid_at')
            ->get(['paid_at', 'utm_campaign', 'total_amount']);

        if ($orders->isEmpty()) {
            return [
                'granularity' => 'hour',
                'labels' => [],
                'series' => [],
            ];
        }

        $labels = collect(range(0, 23))
            ->map(fn (int $hour) => [
                'key' => (string) $hour,
                'label' => self::hourAxisLabel($hour),
            ])
            ->values()
            ->all();

        $topCampaigns = self::topCampaignNamesFromOrders($orders);
        $revenueByHourCampaign = self::campaignRevenueBucketsFromOrders($orders, fn (Carbon $paidAt) => (string) (int) $paidAt->format('G'));

        $series = self::campaignRevenueSeriesFromBuckets($labels, $topCampaigns, $revenueByHourCampaign);

        return [
            'granularity' => 'hour',
            'labels' => $labels,
            'series' => $series,
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     * @param  array<string, string>  $filters
     * @return array{granularity: 'day', labels: list<array{key: string, label: string}>, series: list<array{name: string, points: list<array{revenue: float}>}>}
     */
    private static function donationCampaignRevenueTrendDaily(User $user, array $range, array $filters = []): array
    {
        $ordersQuery = self::attributedPaidOrders($user, $range);
        self::applyDonationDeviceFilter($ordersQuery, $filters);
        $orders = $ordersQuery
            ->whereNotNull('paid_at')
            ->get(['paid_at', 'utm_campaign', 'total_amount']);

        if ($orders->isEmpty()) {
            return [
                'granularity' => 'day',
                'labels' => [],
                'series' => [],
            ];
        }

        $timezone = config('app.timezone');
        $start = $range['start']?->copy()->timezone($timezone)->startOfDay();
        $end = ($range['end'] ?? now())->copy()->timezone($timezone)->startOfDay();

        if ($start === null) {
            $firstPaidAt = $orders->pluck('paid_at')->filter()->min();
            $start = $firstPaidAt instanceof Carbon
                ? $firstPaidAt->copy()->timezone($timezone)->startOfDay()
                : now($timezone)->startOfDay();
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->startOfDay()];
        }

        if ($start->diffInDays($end) > 90) {
            $start = $end->copy()->subDays(89)->startOfDay();
        }

        $labels = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $labels[] = [
                'key' => $cursor->toDateString(),
                'label' => $cursor->format('d M'),
            ];
            $cursor->addDay();
        }

        $topCampaigns = self::topCampaignNamesFromOrders($orders);
        $revenueByDayCampaign = self::campaignRevenueBucketsFromOrders(
            $orders,
            fn (Carbon $paidAt) => $paidAt->timezone($timezone)->toDateString(),
        );

        $series = self::campaignRevenueSeriesFromBuckets($labels, $topCampaigns, $revenueByDayCampaign);

        return [
            'granularity' => 'day',
            'labels' => $labels,
            'series' => $series,
        ];
    }

    private static function isHourlyTrendRange(string $duration, array $range): bool
    {
        if ($duration === 'today') {
            return true;
        }

        $timezone = config('app.timezone');
        $start = $range['start']?->copy()->timezone($timezone)->startOfDay();
        $end = ($range['end'] ?? now())->copy()->timezone($timezone)->startOfDay();

        return $start !== null && $end !== null && $start->equalTo($end);
    }

    private static function hourAxisLabel(int $hour): string
    {
        $period = $hour < 12 ? 'AM' : 'PM';
        $display = $hour % 12;

        if ($display === 0) {
            $display = 12;
        }

        return $display.$period;
    }

    /**
     * @param  Collection<int, DonationOrder>  $orders
     * @return list<string>
     */
    private static function topCampaignNamesFromOrders(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (DonationOrder $order) => filled($order->utm_campaign)
                ? (string) $order->utm_campaign
                : 'Untitled')
            ->map(fn (Collection $group) => round((float) $group->sum('total_amount'), 2))
            ->sortDesc()
            ->take(5)
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, DonationOrder>  $orders
     * @param  callable(Carbon): (int|string)  $bucketKeyResolver
     * @return array<int|string, array<string, float>>
     */
    private static function campaignRevenueBucketsFromOrders(Collection $orders, callable $bucketKeyResolver): array
    {
        $timezone = config('app.timezone');
        $buckets = [];

        foreach ($orders as $order) {
            $paidAt = $order->paid_at?->timezone($timezone);

            if ($paidAt === null) {
                continue;
            }

            $bucket = $bucketKeyResolver($paidAt);
            $campaign = filled($order->utm_campaign) ? (string) $order->utm_campaign : 'Untitled';
            $buckets[$bucket][$campaign] = ($buckets[$bucket][$campaign] ?? 0) + (float) $order->total_amount;
        }

        return $buckets;
    }

    /**
     * @param  list<array{key: string, label: string}>  $labels
     * @param  list<string>  $topCampaigns
     * @param  array<int|string, array<string, float>>  $revenueBuckets
     * @return list<array{name: string, points: list<array{revenue: float}>}>
     */
    private static function campaignRevenueSeriesFromBuckets(array $labels, array $topCampaigns, array $revenueBuckets): array
    {
        return collect($topCampaigns)
            ->map(fn (string $name) => [
                'name' => $name,
                'points' => collect($labels)
                    ->map(fn (array $label) => [
                        'revenue' => round((float) ($revenueBuckets[$label['key']][$name] ?? 0), 2),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     * @param  array<string, string>  $filters
     * @return list<array{name: string, count: float, percentage: float}>
     */
    private static function donationTopCampaignBreakdown(User $user, array $range, array $filters = []): array
    {
        $orders = self::attributedPaidOrders($user, $range);
        self::applyDonationDeviceFilter($orders, $filters);
        $rows = $orders
            ->selectRaw("COALESCE(NULLIF(utm_campaign, ''), 'Untitled') as campaign")
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('campaign')
            ->orderByDesc('revenue')
            ->get();

        $total = round((float) $rows->sum('revenue'), 2);

        if ($total <= 0) {
            return [];
        }

        $top = $rows->take(5)->map(fn ($row) => [
            'name' => (string) $row->campaign,
            'count' => round((float) $row->revenue, 2),
            'percentage' => round(((float) $row->revenue / $total) * 100, 1),
        ]);

        $other = round((float) $rows->slice(5)->sum('revenue'), 2);

        if ($other > 0) {
            $top->push([
                'name' => 'Other',
                'count' => $other,
                'percentage' => round(($other / $total) * 100, 1),
            ]);
        }

        return $top->values()->all();
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     * @return list<array{title: string, donations: int, amount: float}>
     */
    private static function donationTopCauses(User $user, array $range): array
    {
        return DonationItem::query()
            ->whereIn('donation_order_id', (clone self::attributedPaidOrders($user, $range))->select('id'))
            ->leftJoin('causes', 'donation_items.cause_id', '=', 'causes.id')
            ->selectRaw("COALESCE(causes.title, donation_items.cause, 'Unknown') as title")
            ->selectRaw('COUNT(DISTINCT donation_items.donation_order_id) as donations')
            ->selectRaw('SUM(donation_items.amount) as amount')
            ->groupByRaw("COALESCE(causes.title, donation_items.cause, 'Unknown')")
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($cause) => [
                'title' => self::displayCauseTitle((string) $cause->title),
                'donations' => (int) $cause->donations,
                'amount' => (float) $cause->amount,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label?: string}  $range
     * @param  array<string, string>  $filters
     * @return array{data: list<array<string, mixed>>}
     */
    private static function recentAttributedDonations(User $user, array $range, int $limit = 12, array $filters = []): array
    {
        $orders = self::attributedPaidOrders($user, $range);
        self::applyDonationDeviceFilter($orders, $filters);
        $rows = $orders
            ->with('items.causeModel')
            ->latest('paid_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (DonationOrder $order) => self::marketerDonationRow($order))
            ->values()
            ->all();

        return ['data' => $rows];
    }

    /**
     * Distinct donation values for this marketer only, used as filter dropdowns.
     *
     * @return array<string, list<string>>
     */
    private static function donationFilterOptions(User $user): array
    {
        return [
            'utm_campaign' => self::distinctDonationColumn($user, 'utm_campaign'),
            'utm_medium' => self::distinctDonationColumn($user, 'utm_medium'),
            'utm_content' => self::distinctDonationColumn($user, 'utm_content'),
            'city' => self::distinctDonationColumn($user, 'city'),
            'state' => self::distinctDonationColumn($user, 'state'),
            'cause' => self::distinctDonationCauses($user),
            'title' => self::distinctDonationTitles($user),
            'device_type' => self::distinctDonationDevices($user),
        ];
    }

    /**
     * @return list<string>
     */
    private static function distinctDonationDevices(User $user): array
    {
        $devices = collect(self::distinctDonationColumn($user, 'device_type'))
            ->reject(fn (string $value) => $value === 'unknown')
            ->values();

        $hasUnknown = self::attributedPaidOrders($user, ['start' => null, 'end' => null])
            ->where(function (Builder $deviceQuery): void {
                $deviceQuery->whereNull('device_type')
                    ->orWhere('device_type', '')
                    ->orWhere('device_type', 'unknown');
            })
            ->exists();

        if ($hasUnknown) {
            $devices->push('unknown');
        }

        return $devices->all();
    }

    /**
     * @return list<string>
     */
    private static function distinctDonationColumn(User $user, string $column, int $limit = 200): array
    {
        return self::attributedPaidOrders($user, ['start' => null, 'end' => null])
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit($limit)
            ->pluck($column)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function distinctDonationCauses(User $user): array
    {
        return DonationItem::query()
            ->whereIn('donation_order_id', self::attributedPaidOrders($user, ['start' => null, 'end' => null])->select('id'))
            ->leftJoin('causes', 'donation_items.cause_id', '=', 'causes.id')
            ->selectRaw("COALESCE(causes.title, donation_items.cause, '') as cause_title")
            ->distinct()
            ->orderBy('cause_title')
            ->limit(200)
            ->pluck('cause_title')
            ->map(fn ($value) => self::displayCauseTitle((string) $value))
            ->filter(fn (string $value) => $value !== '—')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function distinctDonationTitles(User $user): array
    {
        return DonationItem::query()
            ->whereIn('donation_order_id', self::attributedPaidOrders($user, ['start' => null, 'end' => null])->select('id'))
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->distinct()
            ->orderBy('title')
            ->limit(200)
            ->pluck('title')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Public marketer row: location and item only — never donor identity.
     *
     * @return array<string, mixed>
     */
    private static function marketerDonationRow(DonationOrder $order): array
    {
        $item = $order->items->first();
        $paidAt = $order->paid_at?->timezone(config('app.timezone'));

        return [
            'campaign' => filled($order->utm_campaign) ? (string) $order->utm_campaign : '—',
            'medium' => filled($order->utm_medium) ? (string) $order->utm_medium : '—',
            'ad' => filled($order->utm_content) ? (string) $order->utm_content : '—',
            'cause' => self::displayCauseTitle(
                $item?->causeModel?->title ?? $item?->cause ?? '—',
                $item?->title,
            ),
            'title' => filled($item?->title) ? (string) $item->title : '—',
            'pincode' => filled($order->pincode) ? (string) $order->pincode : '—',
            'city' => filled($order->city) ? (string) $order->city : '—',
            'state' => filled($order->state) ? (string) $order->state : '—',
            'ip_address' => filled($order->ip_address) ? (string) $order->ip_address : '—',
            'ip_location' => self::formatDonationIpLocation($order),
            'device' => self::normalizeDeviceValue($order->device_type),
            'amount' => round((float) $order->total_amount, 2),
            'time' => $paidAt?->format('d M Y, h:i A') ?: '—',
        ];
    }

    private static function formatDonationIpLocation(DonationOrder $order): string
    {
        $parts = array_values(array_filter([
            filled($order->ip_city) ? (string) $order->ip_city : null,
            filled($order->ip_region_name) ? (string) $order->ip_region_name : null,
            filled($order->ip_country_name)
                ? (string) $order->ip_country_name
                : (filled($order->ip_country_code) ? (string) $order->ip_country_code : null),
        ]));

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    /**
     * @return list<string>
     */
    private static function donationExportHeaders(): array
    {
        return [
            'Campaign',
            'Medium',
            'Ad',
            'Cause',
            'Title',
            'Pincode',
            'City',
            'State',
            'IP',
            'IP location',
            'Device',
            'Amount',
            'Time',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function marketerDonationExportRow(DonationOrder $order): array
    {
        $row = self::marketerDonationRow($order);

        return [
            'Campaign' => $row['campaign'],
            'Medium' => $row['medium'],
            'Ad' => $row['ad'],
            'Cause' => $row['cause'],
            'Title' => $row['title'],
            'Pincode' => $row['pincode'],
            'City' => $row['city'],
            'State' => $row['state'],
            'IP' => $row['ip_address'],
            'IP location' => $row['ip_location'],
            'Device' => self::deviceLabel($row['device']),
            'Amount' => $row['amount'],
            'Time' => $row['time'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function visitExportRow(LinkTrackingVisit $visit): array
    {
        $row = self::visitRow($visit);
        $extra = is_array($visit->extra_params) ? $visit->extra_params : [];

        return [
            'When' => $row['created_at'] ?? '',
            'Converted' => $row['converted'] ? 'Yes' : 'No',
            'Donation' => $row['converted_amount'] ?? '',
            'Converted at' => $row['converted_at'] ?? '',
            'IP' => $row['ip_address'] ?? '',
            'Device' => self::deviceLabel($row['device_type'] ?? null),
            'Unique' => $row['is_unique'] ? 'Yes' : 'No',
            'Source' => $row['utm_source'] ?? '',
            'Medium' => $row['utm_medium'] ?? '',
            'Campaign' => $row['utm_campaign'] ?? '',
            'Ad' => $row['utm_content'] ?? '',
            'Referral (sid)' => $row['sid'] ?? '',
            'Ad set ID' => $row['utm_term'] ?? '',
            'Ad ID' => $extra['aid'] ?? ($row['aid'] ?? ''),
            'UTM ID' => $row['utm_id'] ?? '',
            'Page' => $row['page_path'] ?? '',
            'Landing URL' => $row['landing_url'] ?? '',
            'Referrer' => $row['referrer'] ?? '',
        ];
    }

    public static function deviceLabel(?string $device): string
    {
        return match (self::normalizeDeviceValue($device)) {
            'mobile' => 'Mobile',
            'tablet' => 'Tablet',
            'desktop' => 'PC',
            default => 'Unknown',
        };
    }

    private static function normalizeDeviceValue(?string $device): string
    {
        $value = strtolower(trim((string) $device));

        if (in_array($value, ['mobile', 'tablet', 'desktop'], true)) {
            return $value;
        }

        return 'unknown';
    }

    private static function displayCauseTitle(?string $cause, ?string $itemTitle = null): string
    {
        $label = trim((string) $cause);
        $item = trim((string) $itemTitle);

        if (self::looksLikeDailyNeed($label) || self::looksLikeDailyNeed($item)) {
            return 'Daily Need';
        }

        return $label !== '' ? $label : '—';
    }

    private static function looksLikeDailyNeed(string $value): bool
    {
        $normalized = strtolower($value);

        return str_starts_with($normalized, 'daily need')
            || str_starts_with($normalized, 'daily-need');
    }

    public static function attributedVisits(User $user): Builder
    {
        $code = StaffReferral::normalize($user->referral_code);

        if ($code === null) {
            return LinkTrackingVisit::query()->whereRaw('0 = 1');
        }

        return LinkTrackingVisit::query()->where('sid', $code);
    }

    /**
     * @return array<string, string>
     */
    private static function visitFilters(Request $request): array
    {
        $filters = [];

        foreach ([
            'from_date',
            'to_date',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'utm_id',
            'utm_term',
            'device_type',
            'result',
            'page_path',
            'referrer',
            'aid',
        ] as $key) {
            $filters[$key] = trim((string) $request->input($key, ''));
        }

        return $filters;
    }

    /**
     * @param  array<string, string>  $filters
     */
    private static function applyVisitFilters(Builder $query, array $filters): void
    {
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_id', 'utm_term', 'page_path', 'referrer'] as $column) {
            $value = $filters[$column] ?? '';

            if ($value === '') {
                continue;
            }

            $query->where($column, $value);
        }

        $deviceType = $filters['device_type'] ?? '';
        if ($deviceType === 'unknown') {
            $query->where(function (Builder $deviceQuery): void {
                $deviceQuery->whereNull('device_type')
                    ->orWhere('device_type', '')
                    ->orWhere('device_type', 'unknown');
            });
        } elseif ($deviceType !== '') {
            $query->where('device_type', $deviceType);
        }

        $result = $filters['result'] ?? '';
        if ($result === 'donated') {
            $query->where('converted', true);
        } elseif ($result === 'unique') {
            $query->where('is_unique', true)->where('converted', false);
        } elseif ($result === 'repeat') {
            $query->where('is_unique', false)->where('converted', false);
        }

        $aid = $filters['aid'] ?? '';
        if ($aid !== '') {
            $query->where('extra_params->aid', $aid);
        }
    }

    /**
     * Distinct tracking values for this marketer only, used as filter dropdowns.
     *
     * @return array<string, list<string>>
     */
    private static function visitFilterOptions(User $user): array
    {
        return [
            'utm_source' => self::distinctVisitColumn($user, 'utm_source'),
            'utm_medium' => self::distinctVisitColumn($user, 'utm_medium'),
            'utm_campaign' => self::distinctVisitColumn($user, 'utm_campaign'),
            'utm_content' => self::distinctVisitColumn($user, 'utm_content'),
            'utm_id' => self::distinctVisitColumn($user, 'utm_id'),
            'utm_term' => self::distinctVisitColumn($user, 'utm_term'),
            'page_path' => self::distinctVisitColumn($user, 'page_path'),
            'referrer' => self::distinctVisitColumn($user, 'referrer'),
            'aid' => self::distinctVisitAids($user),
            'device_type' => self::distinctVisitDevices($user),
        ];
    }

    /**
     * @return list<string>
     */
    private static function distinctVisitColumn(User $user, string $column, int $limit = 200): array
    {
        return self::attributedVisits($user)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit($limit)
            ->pluck($column)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function distinctVisitDevices(User $user): array
    {
        $devices = collect(self::distinctVisitColumn($user, 'device_type'))
            ->reject(fn (string $value) => $value === 'unknown')
            ->values();

        $hasUnknown = self::attributedVisits($user)
            ->where(function (Builder $deviceQuery): void {
                $deviceQuery->whereNull('device_type')
                    ->orWhere('device_type', '')
                    ->orWhere('device_type', 'unknown');
            })
            ->exists();

        if ($hasUnknown) {
            $devices->push('unknown');
        }

        return $devices->all();
    }

    /**
     * @return list<string>
     */
    private static function distinctVisitAids(User $user, int $limit = 200): array
    {
        return self::attributedVisits($user)
            ->whereNotNull('extra_params')
            ->orderByDesc('id')
            ->limit(2000)
            ->pluck('extra_params')
            ->map(function ($params): string {
                if (! is_array($params)) {
                    return '';
                }

                return trim((string) ($params['aid'] ?? ''));
            })
            ->filter()
            ->unique()
            ->sort()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private static function chartPayload(User $user, array $range, array $filters = []): array
    {
        $clickSummaryFilters = $filters;
        $clickSummaryFilters['result'] = '';
        $visits = self::scopedVisits($user, $range, $clickSummaryFilters);

        return [
            'target' => self::targetProgress($user),
            'dailyTrend' => self::dailyTrend($visits, $range),
            'monthlyTrend' => self::monthlyTrend($user, $filters),
            'campaignBreakdown' => self::campaignBreakdown($user, $range, $filters),
            'deviceBreakdown' => self::countSlices(
                self::visitCollection($visits)->countBy(fn (LinkTrackingVisit $visit) => ucfirst($visit->device_type ?: 'unknown'))
            ),
            'resultBreakdown' => self::countSlices(collect([
                'Donated' => (clone self::attributedPaidOrders($user, $range))->count(),
                'Clicks only' => (clone $visits)->where('converted', false)->count(),
            ])),
        ];
    }

    /**
     * @return array{
     *     goal: ?int,
     *     achieved: int,
     *     remaining: ?int,
     *     achieved_percent: ?float,
     *     remaining_percent: ?float
     * }
     */
    private static function targetProgress(User $user): array
    {
        $goal = $user->donation_target !== null ? (int) $user->donation_target : null;
        $code = StaffReferral::normalize($user->referral_code);
        $achieved = 0;

        if ($code !== null) {
            $achieved = (int) round((float) (LinkTrackingSummary::query()
                ->where('sid', $code)
                ->sum('total_amount') ?? 0));
        }

        if ($goal === null || $goal <= 0) {
            return [
                'goal' => null,
                'achieved' => $achieved,
                'remaining' => null,
                'achieved_percent' => null,
                'remaining_percent' => null,
            ];
        }

        $remaining = max(0, $goal - $achieved);
        $achievedPercent = min(100, round(($achieved / $goal) * 100, 1));

        return [
            'goal' => $goal,
            'achieved' => $achieved,
            'remaining' => $remaining,
            'achieved_percent' => $achievedPercent,
            'remaining_percent' => round(100 - $achievedPercent, 1),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return list<array{key: string, label: string, donations: int, amount: float}>
     */
    private static function monthlyTrend(User $user, array $filters = []): array
    {
        $start = now()->startOfMonth()->subMonths(11);
        $query = self::attributedVisits($user);
        self::applyVisitFilters($query, $filters);
        $rows = $query
            ->where('converted', true)
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'converted_amount'])
            ->groupBy(fn (LinkTrackingVisit $visit) => $visit->created_at?->format('Y-m') ?? '');

        $points = [];
        $cursor = $start->copy();

        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $monthRows = $rows->get($key, collect());

            $points[] = [
                'key' => $key,
                'label' => $cursor->format('M'),
                'donations' => $monthRows->count(),
                'amount' => round((float) $monthRows->sum('converted_amount'), 2),
            ];

            $cursor->addMonth();
        }

        return $points;
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, string>  $filters
     * @return list<array{name: string, count: int, percentage: float}>
     */
    private static function campaignBreakdown(User $user, array $range, array $filters = []): array
    {
        return self::countSlices(
            collect(self::campaignRows($user, $range, $filters))
                ->groupBy(fn (array $row) => (string) ($row['utm_campaign'] ?: 'Untitled'))
                ->map(fn (Collection $group) => (int) $group->sum('clicks'))
        );
    }

    /**
     * @param  Collection<array-key, int>  $counts
     * @return list<array{name: string, count: int, percentage: float}>
     */
    private static function countSlices(Collection $counts): array
    {
        $filtered = $counts->filter(fn ($count) => (int) $count > 0);
        $total = (int) $filtered->sum();

        if ($total === 0) {
            return [];
        }

        $top = $filtered->sortDesc()->take(5);
        $other = (int) $filtered->sortDesc()->slice(5)->sum();

        if ($other > 0) {
            $top = $top->put('Other', $other);
        }

        return $top->map(fn ($count, $name) => [
            'name' => (string) $name,
            'count' => (int) $count,
            'percentage' => round(((int) $count / $total) * 100, 1),
        ])->values()->all();
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, string>  $filters
     * @return list<array<string, mixed>>
     */
    private static function campaignRows(User $user, array $range, array $filters = []): array
    {
        $rows = self::scopedVisits($user, $range, $filters)
            ->selectRaw("COALESCE(utm_campaign, '') as utm_campaign")
            ->selectRaw('COUNT(*) as clicks')
            ->selectRaw('SUM(CASE WHEN is_unique = 1 THEN 1 ELSE 0 END) as unique_visitors')
            ->selectRaw('SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions')
            ->selectRaw('SUM(CASE WHEN converted = 1 THEN COALESCE(converted_amount, 0) ELSE 0 END) as revenue')
            ->selectRaw('MAX(created_at) as last_click_at')
            ->groupByRaw("COALESCE(utm_campaign, '')")
            ->orderByDesc('clicks')
            ->get();

        return $rows->map(function ($row): array {
            $campaign = (string) ($row->utm_campaign ?? '');
            $clicks = (int) $row->clicks;
            $conversions = (int) $row->conversions;
            $revenue = round((float) $row->revenue, 2);

            return [
                'utm_campaign' => $campaign !== '' ? $campaign : null,
                'clicks' => $clicks,
                'unique_visitors' => (int) $row->unique_visitors,
                'conversions' => $conversions,
                'revenue' => $revenue,
                'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 1) : 0,
                'last_click_at' => $row->last_click_at
                    ? Carbon::parse($row->last_click_at)->timezone(config('app.timezone'))->toDateTimeString()
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function adRows(Builder $visits): array
    {
        return self::visitCollection($visits)
            ->groupBy(function (LinkTrackingVisit $visit): string {
                $aid = is_array($visit->extra_params) ? (string) ($visit->extra_params['aid'] ?? '') : '';

                return implode('|', [
                    (string) $visit->utm_id,
                    (string) $visit->utm_campaign,
                    (string) $visit->utm_term,
                    $aid,
                ]);
            })
            ->map(function (Collection $group): array {
                /** @var LinkTrackingVisit $first */
                $first = $group->first();
                $clicks = $group->count();
                $conversions = $group->where('converted', true)->count();
                $revenue = round((float) $group->where('converted', true)->sum('converted_amount'), 2);
                $aid = is_array($first->extra_params) ? ($first->extra_params['aid'] ?? null) : null;

                return [
                    'utm_campaign' => $first->utm_campaign,
                    'utm_id' => $first->utm_id,
                    'utm_term' => $first->utm_term,
                    'sid' => $first->sid,
                    'aid' => $aid,
                    'clicks' => $clicks,
                    'unique_visitors' => $group->where('is_unique', true)->count(),
                    'conversions' => $conversions,
                    'revenue' => $revenue,
                    'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('clicks')
            ->values()
            ->all();
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @return list<array{key: string, label: string, visitors: int, checkouts: int, paid: int}>
     */
    private static function dailyTrend(Builder $visits, array $range): array
    {
        $rows = (clone $visits)
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as clicks')
            ->selectRaw('SUM(CASE WHEN is_unique = 1 THEN 1 ELSE 0 END) as unique_visitors')
            ->selectRaw('SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $start = $range['start']?->copy()->startOfDay() ?? ($rows->isEmpty()
            ? now()->startOfDay()
            : Carbon::parse((string) $rows->keys()->first())->startOfDay());
        $end = $range['end']?->copy()->startOfDay() ?? ($rows->isEmpty()
            ? now()->startOfDay()
            : Carbon::parse((string) $rows->keys()->last())->startOfDay());

        if ($start->diffInDays($end) > 90) {
            $start = $end->copy()->subDays(89)->startOfDay();
        }

        $points = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);

            $points[] = [
                'key' => $key,
                'label' => $cursor->format('d M'),
                'visitors' => (int) ($row->clicks ?? 0),
                'checkouts' => (int) ($row->unique_visitors ?? 0),
                'paid' => (int) ($row->conversions ?? 0),
            ];

            $cursor->addDay();
        }

        return $points;
    }

    /**
     * @param  Collection<int, LinkTrackingVisit>  $visits
     * @return list<array<string, mixed>>
     */
    private static function visitRows(Collection $visits): array
    {
        return $visits->map(fn (LinkTrackingVisit $visit) => self::visitRow($visit))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function visitRow(LinkTrackingVisit $visit): array
    {
        $extra = is_array($visit->extra_params) ? $visit->extra_params : [];

        return [
            'id' => $visit->id,
            'created_at' => $visit->created_at?->timezone(config('app.timezone'))?->toDateTimeString(),
            'converted' => (bool) $visit->converted,
            'converted_amount' => $visit->converted_amount !== null ? round((float) $visit->converted_amount, 2) : null,
            'converted_at' => $visit->converted_at?->timezone(config('app.timezone'))?->toDateTimeString(),
            'ip_address' => $visit->ip_address,
            'device_type' => $visit->device_type,
            'is_unique' => (bool) $visit->is_unique,
            'user_agent' => $visit->user_agent,
            'visitor_id' => $visit->visitor_id,
            'sid' => $visit->sid,
            'utm_source' => $visit->utm_source,
            'utm_medium' => $visit->utm_medium,
            'utm_campaign' => $visit->utm_campaign,
            'utm_content' => $visit->utm_content,
            'utm_term' => $visit->utm_term,
            'utm_id' => $visit->utm_id,
            'aid' => $extra['aid'] ?? null,
            'fbclid' => $visit->fbclid,
            'amt' => $visit->amt,
            'ptype' => $visit->ptype,
            'page_path' => $visit->page_path,
            'landing_url' => $visit->landing_url,
            'referrer' => $visit->referrer,
            'extra_params' => $extra === [] ? null : $extra,
        ];
    }

    /**
     * @return Collection<int, LinkTrackingVisit>
     */
    private static function visitCollection(Builder $visits): Collection
    {
        return (clone $visits)->latest('id')->limit(2000)->get();
    }
}
