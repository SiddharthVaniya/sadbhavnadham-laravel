<?php

namespace App\Support;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationCampaign;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use App\Services\DonationAttributionService;
use App\Support\Attribution\AttributionTaxonomy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminReportsData
{
    public const REPORT_TYPES = [
        'monthly_by_cause' => 'Month-wise totals by cause',
        'cause_summary' => 'Cause-wise donation summary',
        'package_summary' => 'Package-wise donation summary',
        'monthly_summary' => 'Month-wise donation summary',
        'daily_summary' => 'Day-wise donation summary',
        'donation_detail' => 'Detailed donation list',
    ];

    public const DURATION_OPTIONS = [
        'today' => 'Today (daywise)',
        'yesterday' => 'Yesterday (daywise)',
        'this_week' => 'This week',
        'last_week' => 'Previous week',
        'this_month' => 'This month (monthwise)',
        'last_month' => 'Last month (monthwise)',
        'this_fy' => 'Current financial year (Apr–Mar)',
        'last_fy' => 'Previous financial year',
        'ytd' => 'Year to date',
        'last_12_months' => 'Last 12 months',
        'all' => 'All time',
        'custom' => 'Custom date range',
    ];

    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    /** Sentinel for SID filter: organic traffic only. */
    public const PARTNER_FILTER_ORGANIC = 'organic';

    /** Sentinel for SID filter: any partner / SID attributed donation. */
    public const PARTNER_FILTER_ALL = 'all_partners';

    public const CAUSE_MATCH_OPTIONS = [
        '' => 'All donation',
        'match' => 'Match cause',
        'mismatch' => 'Mismatch cause',
    ];

    public static function reportFromRequest(Request $request): array
    {
        $type = (string) $request->input('type', 'monthly_by_cause');

        if (! array_key_exists($type, self::REPORT_TYPES)) {
            $type = 'monthly_by_cause';
        }

        $filters = self::normalizeFilters($request);
        $range = self::resolveRange($request, $filters['duration']);

        return match ($type) {
            'cause_summary' => self::buildCauseSummary($range, $filters),
            'package_summary' => self::buildPackageSummary($range, $filters),
            'monthly_summary' => self::buildMonthlySummary($range, $filters),
            'daily_summary' => self::buildDailySummary($range, $filters),
            'donation_detail' => self::buildDonationDetail($range, $filters),
            default => self::buildMonthlyByCause($range, $filters),
        };
    }

    /**
     * @return array{
     *     duration: string,
     *     cause_id: ?int,
     *     state: ?string,
     *     source: ?string,
     *     partner_user_id: int|string|null,
     *     partner_organic: bool,
     *     partner_all: bool,
     *     partner: ?User,
     *     cause_match: string,
     *     from_date: ?string,
     *     to_date: ?string
     * }
     */
    public static function normalizeFilters(Request $request): array
    {
        $duration = (string) $request->input('duration', 'this_fy');
        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = 'this_fy';
        }

        $state = is_string($request->input('state')) ? trim($request->input('state')) : null;
        if ($state === '') {
            $state = null;
        }

        $source = is_string($request->input('source')) ? mb_strtolower(trim($request->input('source'))) : null;
        if ($source === '' || ! array_key_exists($source, DonationAttributionService::trafficSourceOptions())) {
            $source = null;
        }

        $causeMatch = is_string($request->input('cause_match'))
            ? mb_strtolower(trim($request->input('cause_match')))
            : '';
        if (! array_key_exists($causeMatch, self::CAUSE_MATCH_OPTIONS)) {
            $causeMatch = '';
        }

        $partnerRaw = $request->input('partner_user_id');
        $partnerRawNormalized = is_string($partnerRaw) || is_numeric($partnerRaw)
            ? mb_strtolower(trim((string) $partnerRaw))
            : '';

        $partnerOrganic = $partnerRawNormalized === self::PARTNER_FILTER_ORGANIC;
        $partnerAll = $partnerRawNormalized === self::PARTNER_FILTER_ALL;

        $partnerUserId = null;
        $partner = null;

        if ($partnerOrganic) {
            $partnerUserId = self::PARTNER_FILTER_ORGANIC;
        } elseif ($partnerAll) {
            $partnerUserId = self::PARTNER_FILTER_ALL;
        } elseif ($request->filled('partner_user_id')) {
            $partnerUserId = (int) $request->input('partner_user_id');
            $partner = User::query()
                ->whereKey($partnerUserId)
                ->whereNotNull('referral_code')
                ->where('referral_code', '!=', '')
                ->first();

            if (! $partner) {
                $partnerUserId = null;
            }
        }

        return [
            'duration' => $duration,
            'cause_id' => $request->filled('cause_id') ? (int) $request->input('cause_id') : null,
            'state' => $state,
            'source' => $source,
            'partner_user_id' => $partnerUserId,
            'partner_organic' => $partnerOrganic,
            'partner_all' => $partnerAll,
            'partner' => $partner,
            'cause_match' => $causeMatch,
            'from_date' => is_string($request->input('from_date')) ? $request->input('from_date') : null,
            'to_date' => is_string($request->input('to_date')) ? $request->input('to_date') : null,
        ];
    }

    /**
     * @return array{
     *     states: list<string>,
     *     partners: list<array{id: int, name: string, code: string}>,
     *     sources: list<array{value: string, label: string}>
     * }
     */
    public static function filterOptions(): array
    {
        return [
            'states' => DonationOrder::query()
                ->where('status', DonationOrder::STATUS_PAID)
                ->whereNotNull('state')
                ->where('state', '!=', '')
                ->distinct()
                ->orderBy('state')
                ->pluck('state')
                ->filter()
                ->values()
                ->all(),
            'partners' => User::query()
                ->whereNotNull('referral_code')
                ->where('referral_code', '!=', '')
                ->orderBy('name')
                ->get(['id', 'name', 'referral_code'])
                ->map(static fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'code' => (string) $user->referral_code,
                ])
                ->values()
                ->all(),
            'sources' => collect(DonationAttributionService::trafficSourceOptions())
                ->map(static fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
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

            return self::rangeForDuration('this_fy');
        }

        return self::rangeForDuration($duration);
    }

    /**
     * @return array{start: ?Carbon, end: ?Carbon, label: string}
     */
    public static function rangeForDuration(string $duration): array
    {
        if ($calendar = PeriodRange::forKey($duration)) {
            return $calendar;
        }

        $now = now();

        return match ($duration) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'label' => 'Today · '.$now->format('d M Y'),
            ],
            'this_fy' => self::financialYearRange($now),
            'last_fy' => self::financialYearRange($now->copy()->subYear()),
            'ytd' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfDay(),
                'label' => 'Year to date '.$now->year,
            ],
            'last_12_months' => [
                'start' => $now->copy()->subMonths(11)->startOfMonth(),
                'end' => $now->copy()->endOfDay(),
                'label' => 'Last 12 months',
            ],
            default => [
                'start' => null,
                'end' => null,
                'label' => 'All time',
            ],
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    private static function financialYearRange(Carbon $reference): array
    {
        [$start, $end] = IndianFinancialYear::range($reference);

        return [
            'start' => $start,
            'end' => $end,
            'label' => 'FY '.$start->year.'–'.substr((string) ($start->year + 1), -2),
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function buildMonthlyByCause(array $range, array $filters): array
    {
        $causeId = $filters['cause_id'] ?? null;
        $causes = self::causesForReport($causeId);
        $monthKeys = self::monthKeysForRange($range['start'], $range['end'], $filters);
        $totals = self::paidItemsByMonthAndCause($range, $filters);

        $uncategorized = $causeId ? [] : self::uncategorizedOrdersByMonth($range, $filters);
        $hasUncategorized = array_sum(array_column($uncategorized, 'amount')) > 0;

        $headers = array_merge(
            ['Month'],
            $causes->pluck('title')->all(),
            $hasUncategorized ? ['Uncategorized'] : [],
            ['Total']
        );

        $rows = [];
        $columnTotals = array_fill_keys($causes->pluck('id')->all(), 0.0);
        $uncategorizedTotal = 0.0;
        $grandTotal = 0.0;

        foreach ($monthKeys as $monthKey) {
            $values = [];
            $rowTotal = 0.0;

            foreach ($causes as $cause) {
                $amount = (float) ($totals[$monthKey][$cause->id] ?? 0);
                $values[] = $amount;
                $columnTotals[$cause->id] += $amount;
                $rowTotal += $amount;
            }

            if ($hasUncategorized) {
                $amount = (float) ($uncategorized[$monthKey]['amount'] ?? 0);
                $values[] = $amount;
                $uncategorizedTotal += $amount;
                $rowTotal += $amount;
            }

            $grandTotal += $rowTotal;

            $rows[] = [
                'key' => $monthKey,
                'label' => Carbon::createFromFormat('Y-m', $monthKey)->format('M Y'),
                'values' => $values,
                'total' => $rowTotal,
            ];
        }

        $totalValues = array_values($columnTotals);

        if ($hasUncategorized) {
            $totalValues[] = $uncategorizedTotal;
        }

        $rows[] = [
            'key' => 'total',
            'label' => 'Total',
            'values' => $totalValues,
            'total' => $grandTotal,
            'is_total_row' => true,
        ];

        return [
            'type' => 'monthly_by_cause',
            'title' => self::REPORT_TYPES['monthly_by_cause'],
            'periodLabel' => $range['label'],
            'headers' => $headers,
            'rows' => $rows,
            'grandTotal' => $grandTotal,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function buildCauseSummary(array $range, array $filters): array
    {
        $causeId = $filters['cause_id'] ?? null;
        $query = self::paidItemsQuery($range, $filters);

        $rows = $query
            ->select('donation_items.cause_id')
            ->selectRaw('COUNT(DISTINCT donation_orders.id) as donation_count')
            ->selectRaw('SUM(donation_items.amount) as total_amount')
            ->groupBy('donation_items.cause_id')
            ->orderByDesc('total_amount')
            ->get();

        $causes = Cause::query()
            ->whereIn('id', $rows->pluck('cause_id'))
            ->pluck('title', 'id');

        $tableRows = $rows->map(function ($row) use ($causes) {
            $count = (int) $row->donation_count;
            $total = (float) $row->total_amount;

            return [
                'cause' => $causes[$row->cause_id] ?? 'Unknown',
                'donation_count' => $count,
                'total_amount' => $total,
                'average_amount' => $count > 0 ? round($total / $count, 2) : 0.0,
            ];
        })->values()->all();

        if (! $causeId) {
            $uncategorized = self::uncategorizedOrdersByMonth($range, $filters);
            $uncategorizedCount = array_sum(array_column($uncategorized, 'count'));
            $uncategorizedAmount = array_sum(array_column($uncategorized, 'amount'));

            if ($uncategorizedCount > 0) {
                $tableRows[] = [
                    'cause' => 'Uncategorized (QR / no cause)',
                    'donation_count' => $uncategorizedCount,
                    'total_amount' => $uncategorizedAmount,
                    'average_amount' => round($uncategorizedAmount / $uncategorizedCount, 2),
                ];
            }
        }

        $grandTotal = array_sum(array_column($tableRows, 'total_amount'));

        return [
            'type' => 'cause_summary',
            'title' => self::REPORT_TYPES['cause_summary'],
            'periodLabel' => $range['label'],
            'headers' => ['Cause', 'Donations', 'Total (INR)', 'Average (INR)'],
            'rows' => $tableRows,
            'grandTotal' => $grandTotal,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function buildPackageSummary(array $range, array $filters): array
    {
        $causeId = $filters['cause_id'] ?? null;

        $rows = self::paidItemsQuery($range, $filters)
            ->whereNotNull('donation_items.cause_package_id')
            ->select('donation_items.cause_package_id')
            ->selectRaw('COUNT(DISTINCT donation_orders.id) as donation_count')
            ->selectRaw('SUM(donation_items.amount) as total_amount')
            ->groupBy('donation_items.cause_package_id')
            ->orderByDesc('total_amount')
            ->get();

        $packages = CausePackage::query()
            ->whereIn('id', $rows->pluck('cause_package_id'))
            ->with('cause:id,title')
            ->get()
            ->keyBy('id');

        $tableRows = $rows->map(function ($row) use ($packages) {
            $count = (int) $row->donation_count;
            $total = (float) $row->total_amount;
            $package = $packages->get($row->cause_package_id);

            return [
                'package' => $package?->title ?? 'Unknown package',
                'cause' => $package?->cause?->title ?? '—',
                'donation_count' => $count,
                'total_amount' => $total,
                'average_amount' => $count > 0 ? round($total / $count, 2) : 0.0,
            ];
        })->values()->all();

        $custom = self::paidItemsQuery($range, $filters)
            ->whereNull('donation_items.cause_package_id')
            ->selectRaw('COUNT(DISTINCT donation_orders.id) as donation_count')
            ->selectRaw('SUM(donation_items.amount) as total_amount')
            ->first();

        $customCount = (int) ($custom?->donation_count ?? 0);
        $customAmount = (float) ($custom?->total_amount ?? 0);

        if ($customCount > 0) {
            $tableRows[] = [
                'package' => 'Custom amount / no package',
                'cause' => $causeId ? (Cause::query()->find($causeId)?->title ?? '—') : 'All causes',
                'donation_count' => $customCount,
                'total_amount' => $customAmount,
                'average_amount' => round($customAmount / $customCount, 2),
            ];
        }

        if (! $causeId) {
            $uncategorized = self::uncategorizedOrdersByMonth($range, $filters);
            $uncategorizedCount = array_sum(array_column($uncategorized, 'count'));
            $uncategorizedAmount = array_sum(array_column($uncategorized, 'amount'));

            if ($uncategorizedCount > 0) {
                $tableRows[] = [
                    'package' => 'Uncategorized (QR / no cause)',
                    'cause' => '—',
                    'donation_count' => $uncategorizedCount,
                    'total_amount' => $uncategorizedAmount,
                    'average_amount' => round($uncategorizedAmount / $uncategorizedCount, 2),
                ];
            }
        }

        $grandTotal = array_sum(array_column($tableRows, 'total_amount'));

        return [
            'type' => 'package_summary',
            'title' => self::REPORT_TYPES['package_summary'],
            'periodLabel' => $range['label'],
            'headers' => ['Package', 'Cause', 'Donations', 'Total (INR)', 'Average (INR)'],
            'rows' => $tableRows,
            'grandTotal' => $grandTotal,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function buildMonthlySummary(array $range, array $filters): array
    {
        $causeId = $filters['cause_id'] ?? null;
        $monthExpression = self::monthExpression('donation_orders.paid_at');

        if ($causeId) {
            $rows = self::paidItemsQuery($range, $filters)
                ->selectRaw("{$monthExpression} as month_key")
                ->selectRaw('COUNT(DISTINCT donation_orders.id) as donation_count')
                ->selectRaw('SUM(donation_items.amount) as total_amount')
                ->groupByRaw($monthExpression)
                ->orderByRaw($monthExpression)
                ->get();
        } else {
            $rows = self::paidOrdersQuery($range, $filters)
                ->selectRaw("{$monthExpression} as month_key")
                ->selectRaw('COUNT(*) as donation_count')
                ->selectRaw('SUM(donation_orders.total_amount) as total_amount')
                ->groupByRaw($monthExpression)
                ->orderByRaw($monthExpression)
                ->get();
        }

        $tableRows = $rows->map(function ($row) {
            $count = (int) $row->donation_count;
            $total = (float) $row->total_amount;

            return [
                'month' => Carbon::createFromFormat('Y-m', $row->month_key)->format('M Y'),
                'month_key' => $row->month_key,
                'donation_count' => $count,
                'total_amount' => $total,
                'average_amount' => $count > 0 ? round($total / $count, 2) : 0.0,
            ];
        })->values()->all();

        $grandTotal = array_sum(array_column($tableRows, 'total_amount'));

        return [
            'type' => 'monthly_summary',
            'title' => self::REPORT_TYPES['monthly_summary'],
            'periodLabel' => $range['label'],
            'headers' => ['Month', 'Donations', 'Total (INR)', 'Average (INR)'],
            'rows' => $tableRows,
            'grandTotal' => $grandTotal,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function buildDailySummary(array $range, array $filters): array
    {
        $causeId = $filters['cause_id'] ?? null;
        $dayExpression = self::dayExpression('donation_orders.paid_at');

        if ($causeId) {
            $rows = self::paidItemsQuery($range, $filters)
                ->selectRaw("{$dayExpression} as day_key")
                ->selectRaw('COUNT(DISTINCT donation_orders.id) as donation_count')
                ->selectRaw('SUM(donation_items.amount) as total_amount')
                ->groupByRaw($dayExpression)
                ->orderByRaw($dayExpression)
                ->get();
        } else {
            $rows = self::paidOrdersQuery($range, $filters)
                ->selectRaw("{$dayExpression} as day_key")
                ->selectRaw('COUNT(*) as donation_count')
                ->selectRaw('SUM(donation_orders.total_amount) as total_amount')
                ->groupByRaw($dayExpression)
                ->orderByRaw($dayExpression)
                ->get();
        }

        $tableRows = $rows->map(function ($row) {
            $count = (int) $row->donation_count;
            $total = (float) $row->total_amount;

            return [
                'day' => Carbon::parse($row->day_key)->format('d M Y'),
                'day_key' => $row->day_key,
                'donation_count' => $count,
                'total_amount' => $total,
                'average_amount' => $count > 0 ? round($total / $count, 2) : 0.0,
            ];
        })->values()->all();

        $grandTotal = array_sum(array_column($tableRows, 'total_amount'));

        return [
            'type' => 'daily_summary',
            'title' => self::REPORT_TYPES['daily_summary'],
            'periodLabel' => $range['label'],
            'headers' => ['Day', 'Donations', 'Total (INR)', 'Average (INR)'],
            'rows' => $tableRows,
            'grandTotal' => $grandTotal,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function buildDonationDetail(array $range, array $filters): array
    {
        $query = DonationOrder::query()
            ->with(['items.causeModel', 'partner:id,name,referral_code'])
            ->where('status', DonationOrder::STATUS_PAID)
            ->whereNotNull('paid_at');

        if ($range['start'] && $range['end']) {
            $query->whereBetween('paid_at', [$range['start'], $range['end']]);
        }

        if ($filters['cause_id'] ?? null) {
            $causeId = (int) $filters['cause_id'];
            $query->whereHas('items', fn ($itemQuery) => $itemQuery->where('cause_id', $causeId));
        }

        self::applyOrderFilters($query, $filters, 'donation_orders');

        $orders = $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        $rows = $orders->map(function (DonationOrder $order) {
            $item = $order->items->first();
            $sid = StaffReferral::normalize($order->partner_code)
                ?? StaffReferral::normalize($order->utm_content)
                ?? ($order->partner?->referral_code ? StaffReferral::normalize($order->partner->referral_code) : null);

            return [
                'paid_at' => $order->paid_at?->format('d M Y'),
                'order_uuid' => $order->order_uuid,
                'donor_name' => $order->donor_name,
                'donor_email' => $order->donor_email,
                'donor_phone' => $order->donor_phone,
                'state' => $order->state ?: '—',
                'partner_name' => $order->partner?->name ?: '—',
                'sid_code' => $sid ?: '—',
                'cause' => $item?->causeModel?->title ?? $item?->cause ?? '—',
                'item_title' => $item?->title ?? '—',
                'amount' => (float) $order->total_amount,
                'payment_provider' => $order->payment_provider ?: '—',
                'receipt_number' => $order->hasReceipt() ? $order->receiptNumberFormatted() : '—',
            ];
        })->values()->all();

        $grandTotal = array_sum(array_column($rows, 'amount'));

        return [
            'type' => 'donation_detail',
            'title' => self::REPORT_TYPES['donation_detail'],
            'periodLabel' => $range['label'],
            'headers' => [
                'Paid Date',
                'Order UUID',
                'Donor Name',
                'Email',
                'Phone',
                'State',
                'Partner',
                'SID code',
                'Cause',
                'Item',
                'Amount (INR)',
                'Provider',
                'Receipt',
            ],
            'rows' => $rows,
            'grandTotal' => $grandTotal,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ];
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, array<int, float>>
     */
    private static function paidItemsByMonthAndCause(array $range, array $filters): array
    {
        $monthExpression = self::monthExpression('donation_orders.paid_at');

        $rows = self::paidItemsQuery($range, $filters)
            ->selectRaw("{$monthExpression} as month_key")
            ->addSelect('donation_items.cause_id')
            ->selectRaw('SUM(donation_items.amount) as total_amount')
            ->groupByRaw("{$monthExpression}, donation_items.cause_id")
            ->orderByRaw($monthExpression)
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[$row->month_key][$row->cause_id] = (float) $row->total_amount;
        }

        return $totals;
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     */
    private static function paidOrdersQuery(array $range, array $filters = [])
    {
        $query = DonationOrder::query()
            ->from('donation_orders')
            ->where('donation_orders.status', DonationOrder::STATUS_PAID)
            ->whereNotNull('donation_orders.paid_at');

        if ($range['start'] && $range['end']) {
            $query->whereBetween('donation_orders.paid_at', [$range['start'], $range['end']]);
        }

        self::applyOrderFilters($query, $filters, 'donation_orders');

        return $query;
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, array{count: int, amount: float}>
     */
    private static function uncategorizedOrdersByMonth(array $range, array $filters = []): array
    {
        $monthExpression = self::monthExpression('donation_orders.paid_at');

        $rows = self::paidOrdersQuery($range, $filters)
            ->whereDoesntHave('items', fn ($itemQuery) => $itemQuery->whereNotNull('cause_id'))
            ->selectRaw("{$monthExpression} as month_key")
            ->selectRaw('COUNT(*) as donation_count')
            ->selectRaw('SUM(donation_orders.total_amount) as total_amount')
            ->groupByRaw($monthExpression)
            ->orderByRaw($monthExpression)
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[$row->month_key] = [
                'count' => (int) $row->donation_count,
                'amount' => (float) $row->total_amount,
            ];
        }

        return $totals;
    }

    /**
     * @param  array{start: ?Carbon, end: ?Carbon, label: string}  $range
     * @param  array<string, mixed>  $filters
     */
    private static function paidItemsQuery(array $range, array $filters = [])
    {
        $query = DonationItem::query()
            ->join('donation_orders', 'donation_items.donation_order_id', '=', 'donation_orders.id')
            ->where('donation_orders.status', DonationOrder::STATUS_PAID)
            ->whereNotNull('donation_orders.paid_at')
            ->whereNotNull('donation_items.cause_id');

        if ($range['start'] && $range['end']) {
            $query->whereBetween('donation_orders.paid_at', [$range['start'], $range['end']]);
        }

        if ($filters['cause_id'] ?? null) {
            $query->where('donation_items.cause_id', (int) $filters['cause_id']);
        }

        self::applyOrderFilters($query, $filters, 'donation_orders');

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function applyOrderFilters(mixed $query, array $filters, string $table = 'donation_orders'): void
    {
        if (filled($filters['state'] ?? null)) {
            $query->where($table.'.state', $filters['state']);
        }

        if (filled($filters['source'] ?? null)) {
            DonationAttributionService::applyTrafficSourceFilter($query, (string) $filters['source']);
        }

        self::applyCauseMatchFilter($query, $filters, $table);

        if ($filters['partner_organic'] ?? false) {
            DonationAttributionService::applyTrafficSourceFilter(
                $query,
                AttributionTaxonomy::SOURCE_ORGANIC,
            );

            return;
        }

        if ($filters['partner_all'] ?? false) {
            $query->where(function ($attributed) use ($table) {
                $attributed->whereNotNull($table.'.partner_user_id')
                    ->orWhere(function ($code) use ($table) {
                        $code->whereNotNull($table.'.partner_code')
                            ->where($table.'.partner_code', '!=', '');
                    })
                    ->orWhere(function ($tracked) use ($table) {
                        $tracked->whereNotNull($table.'.utm_content')
                            ->where($table.'.utm_content', '!=', '');
                    })
                    ->orWhere($table.'.utm_source', AttributionTaxonomy::SOURCE_STAFF)
                    ->orWhere($table.'.attr_source', AttributionTaxonomy::SOURCE_STAFF);
            });

            return;
        }

        $partner = $filters['partner'] ?? null;
        if (! $partner instanceof User) {
            return;
        }

        $code = StaffReferral::normalize($partner->referral_code);
        $name = trim($partner->name);

        $query->where(function ($builder) use ($table, $partner, $code, $name) {
            $builder->where(function ($exact) use ($table, $partner) {
                $exact->whereNotNull($table.'.partner_user_id')
                    ->where($table.'.partner_user_id', $partner->id);
            });

            $builder->orWhere(function ($legacy) use ($table, $code, $name) {
                $legacy->whereNull($table.'.partner_user_id');

                $legacy->where(function ($inner) use ($table, $code, $name) {
                    $hasMatch = false;

                    if ($code !== null) {
                        $inner->where($table.'.partner_code', $code)
                            ->orWhere($table.'.utm_content', $code);
                        $hasMatch = true;
                    }

                    if ($name !== '') {
                        $like = '%'.addcslashes($name, '%_\\').'%';
                        if ($hasMatch) {
                            $inner->orWhere($table.'.utm_content', 'like', $like)
                                ->orWhere($table.'.utm_campaign', 'like', $like);
                        } else {
                            $inner->where($table.'.utm_content', 'like', $like)
                                ->orWhere($table.'.utm_campaign', 'like', $like);
                        }
                        $hasMatch = true;
                    }

                    if (! $hasMatch) {
                        $inner->whereRaw('0 = 1');
                    }
                });
            });
        });
    }

    /**
     * Filter by landing-cause vs donated-cause match.
     *
     * Match: landed on cause A and donated to cause A.
     * Mismatch: landed on cause A but donated to a different cause.
     *
     * @param  array<string, mixed>  $filters
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, DonationCampaign>>|null  $campaignIdsByCause
     */
    private static function applyCauseMatchFilter(mixed $query, array $filters, string $table = 'donation_orders'): void
    {
        $mode = (string) ($filters['cause_match'] ?? '');
        if (! in_array($mode, ['match', 'mismatch'], true)) {
            return;
        }

        $causes = Cause::query()->orderBy('id')->get(['id', 'slug']);
        if ($causes->isEmpty()) {
            $query->whereRaw('0 = 1');

            return;
        }

        $campaignIdsByCause = DonationCampaign::query()
            ->whereNotNull('cause_id')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get(['id', 'cause_id', 'slug'])
            ->groupBy('cause_id');

        $isItemQuery = method_exists($query, 'getModel') && $query->getModel() instanceof DonationItem;

        $query->where(function ($outer) use ($mode, $causes, $campaignIdsByCause, $table, $isItemQuery) {
            foreach ($causes as $landingCause) {
                $outer->orWhere(function ($row) use ($mode, $landingCause, $campaignIdsByCause, $table, $isItemQuery) {
                    self::constrainLandingPathToCause($row, $table, $landingCause, $campaignIdsByCause);

                    if ($mode === 'match') {
                        if ($isItemQuery) {
                            $row->where('donation_items.cause_id', $landingCause->id);
                        } else {
                            $row->whereHas('items', static fn ($items) => $items->where('cause_id', $landingCause->id));
                        }

                        return;
                    }

                    if ($isItemQuery) {
                        $row->whereNotNull('donation_items.cause_id')
                            ->where('donation_items.cause_id', '!=', $landingCause->id);
                    } else {
                        $row->whereHas(
                            'items',
                            static fn ($items) => $items->whereNotNull('cause_id')->where('cause_id', '!=', $landingCause->id),
                        )->whereDoesntHave(
                            'items',
                            static fn ($items) => $items->where('cause_id', $landingCause->id),
                        );
                    }
                });
            }
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, DonationCampaign>>  $campaignIdsByCause
     */
    private static function constrainLandingPathToCause(
        mixed $query,
        string $table,
        Cause $cause,
        $campaignIdsByCause,
    ): void {
        $query->where(function ($path) use ($table, $cause, $campaignIdsByCause) {
            foreach (LandingCauseResolver::likePatternsForSlug($cause->slug) as $like) {
                $path->orWhere($table.'.landing_path', 'like', $like);
            }

            $campaigns = $campaignIdsByCause->get($cause->id, collect());
            foreach ($campaigns as $campaign) {
                $campEscaped = addcslashes((string) $campaign->slug, '%_\\');
                $path->orWhere($table.'.landing_path', 'like', '%/give/'.$campEscaped)
                    ->orWhere($table.'.landing_path', 'like', '%/give/'.$campEscaped.'?%')
                    ->orWhere($table.'.landing_path', 'like', '%/give/'.$campEscaped.'/%')
                    ->orWhere($table.'.landing_path', 'like', 'give/'.$campEscaped.'%');
                $path->orWhere($table.'.source_campaign_id', $campaign->id);
            }
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, Cause>
     */
    private static function causesForReport(?int $causeId)
    {
        $query = Cause::query()->orderBy('sort_order')->orderBy('title');

        if ($causeId) {
            $query->where('id', $causeId);
        }

        return $query->get(['id', 'title']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private static function monthKeysForRange(?Carbon $start, ?Carbon $end, array $filters = []): array
    {
        if (! $start || ! $end) {
            $monthExpression = self::monthExpression('donation_orders.paid_at');

            return self::paidOrdersQuery([
                'start' => null,
                'end' => null,
                'label' => 'All time',
            ], $filters)
                ->selectRaw("{$monthExpression} as month_key")
                ->groupByRaw($monthExpression)
                ->orderByRaw($monthExpression)
                ->pluck('month_key')
                ->all();
        }

        $keys = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor <= $last) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }

    private static function monthExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private static function dayExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', {$column})"
            : "DATE({$column})";
    }
}
