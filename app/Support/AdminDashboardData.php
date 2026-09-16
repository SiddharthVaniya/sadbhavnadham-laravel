<?php

namespace App\Support;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardData
{
    public const RECENT_DONATIONS_PER_PAGE = 5;

    public const UPCOMING_BIRTHDAYS_PER_PAGE = 5;

    public static function stats(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $causeCount = Cause::query()->count();
        $activeCauseCount = Cause::query()->where('is_active', true)->count();
        $packageCount = CausePackage::query()->count();
        $activePackageCount = CausePackage::query()->where('is_active', true)->count();

        $paidQuery = DonationOrder::query()->where('status', DonationOrder::STATUS_PAID);

        $totalDonations = (clone $paidQuery)->count();
        $totalDonationAmount = (float) (clone $paidQuery)->sum('total_amount');

        $todayDonations = (clone $paidQuery)
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->count();
        $todayAmount = (float) (clone $paidQuery)
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->sum('total_amount');

        $thisMonthDonations = (clone $paidQuery)
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->count();
        $thisMonthAmount = (float) (clone $paidQuery)
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('total_amount');

        $statusCounts = DonationOrder::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $paidCount = (int) ($statusCounts[DonationOrder::STATUS_PAID] ?? 0);
        $pendingCount = (int) ($statusCounts[DonationOrder::STATUS_PENDING] ?? 0);
        $failedCount = (int) ($statusCounts[DonationOrder::STATUS_FAILED] ?? 0);
        $statusTotal = $paidCount + $pendingCount + $failedCount;

        $percent = fn (int $count): float => $statusTotal > 0
            ? round(($count / $statusTotal) * 100, 1)
            : 0.0;

        return [
            'causeCount' => $causeCount,
            'activeCauseCount' => $activeCauseCount,
            'packageCount' => $packageCount,
            'activePackageCount' => $activePackageCount,
            'causeHealthPercent' => $causeCount > 0 ? (int) round(($activeCauseCount / $causeCount) * 100) : 0,
            'packageHealthPercent' => $packageCount > 0 ? (int) round(($activePackageCount / $packageCount) * 100) : 0,
            'donorCount' => Donor::query()->count(),
            'totalDonations' => $totalDonations,
            'totalDonationAmount' => $totalDonationAmount,
            'todayDonations' => $todayDonations,
            'todayAmount' => $todayAmount,
            'thisMonthDonations' => $thisMonthDonations,
            'thisMonthAmount' => $thisMonthAmount,
            'totalDonationItems' => \App\Models\DonationItem::query()->count(),
            'averageDonationAmount' => (float) ((clone $paidQuery)->average('total_amount') ?? 0),
            'totalAttempts' => $statusTotal,
            'statusCounts' => [
                'paid' => $paidCount,
                'pending' => $pendingCount,
                'failed' => $failedCount,
            ],
            'statusPercentages' => [
                'paid' => $percent($paidCount),
                'pending' => $percent($pendingCount),
                'failed' => $percent($failedCount),
            ],
            'updatedAt' => now()->format('d M Y, h:i A'),
            'awaitingNudgeCount' => DonationOrder::query()
                ->abandonedCheckouts()
                ->where('created_at', '<=', now()->subMinutes(5))
                ->whereNull('payment_link_sent_at')
                ->count(),
        ];
    }

    public static function monthlyTrend(): array
    {
        $start = now()->subMonths(11)->startOfMonth();

        $query = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->where('paid_at', '>=', $start);

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

    public static function recentDonationsPaginated(int $page = 1, array $query = []): array
    {
        $paginator = DonationOrder::query()
            ->with('items.causeModel')
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::RECENT_DONATIONS_PER_PAGE, ['*'], 'recent_page', $page);

        if ($query !== []) {
            $paginator->appends($query);
        }

        return AdminInertiaResources::paginated($paginator, fn (DonationOrder $order) => [
            ...AdminInertiaData::donationRow($order),
            'show_url' => route('admin.donations.show', $order),
        ], 1);
    }

    public static function topDonors(): array
    {
        return Donor::query()
            ->where('name', '!=', '')
            ->whereHas('donationOrders', fn ($query) => $query->where('status', DonationOrder::STATUS_PAID))
            ->withCount(['donationOrders as orders' => fn ($query) => $query->where('status', DonationOrder::STATUS_PAID)])
            ->withSum(['donationOrders as amount' => fn ($query) => $query->where('status', DonationOrder::STATUS_PAID)], 'total_amount')
            ->orderByDesc('amount')
            ->take(5)
            ->get()
            ->map(fn (Donor $donor) => [
                'donor_name' => $donor->name,
                'donor_email' => $donor->email,
                'amount' => (float) ($donor->amount ?? 0),
                'orders' => (int) ($donor->orders ?? 0),
            ])
            ->values()
            ->all();
    }

    public static function topCauses(): array
    {
        // Backwards-compatible alias for overall top causes (no month filter).
        $range = self::monthRange(null);

        return self::topCausesForRange($range['start'], $range['end']);
    }

    public static function birthdays(): array
    {
        $windowDays = collect(range(0, 7))
            ->map(fn (int $offset) => today()->addDays($offset));

        $birthdayDonors = Donor::query()
            ->whereNotNull('date_of_birth')
            ->where('name', '!=', '')
            ->where(function ($query) use ($windowDays): void {
                foreach ($windowDays as $day) {
                    $query->orWhere(function ($dayQuery) use ($day): void {
                        $dayQuery
                            ->whereMonth('date_of_birth', $day->month)
                            ->whereDay('date_of_birth', $day->day);
                    });
                }
            })
            ->whereHas('donationOrders', fn ($query) => $query->where('status', DonationOrder::STATUS_PAID))
            ->withCount(['donationOrders as orders' => fn ($query) => $query->where('status', DonationOrder::STATUS_PAID)])
            ->withSum(['donationOrders as lifetime_amount' => fn ($query) => $query->where('status', DonationOrder::STATUS_PAID)], 'total_amount')
            ->with(['latestPaidDonationOrder', 'latestDonationOrder'])
            ->limit(500)
            ->get()
            ->map(function (Donor $donor): array {
                $latestOrder = $donor->latestPaidDonationOrder ?? $donor->latestDonationOrder;
                $dateOfBirth = $donor->date_of_birth;
                $nextBirthday = $dateOfBirth?->copy()->year(today()->year);

                if ($nextBirthday && $nextBirthday->isPast() && ! $nextBirthday->isToday()) {
                    $nextBirthday = $nextBirthday->addYear();
                }

                return [
                    'donor_name' => $donor->name,
                    'donor_email' => $donor->email,
                    'age' => $dateOfBirth?->age,
                    'orders' => (int) ($donor->orders ?? 0),
                    'lifetime_amount' => (float) ($donor->lifetime_amount ?? 0),
                    'days_until_birthday' => $nextBirthday ? (int) today()->diffInDays($nextBirthday, false) : null,
                    'next_birthday_label' => $nextBirthday?->format('d M'),
                    'is_today' => $dateOfBirth?->isBirthday(today()) ?? false,
                    'show_url' => $latestOrder ? route('admin.donations.show', $latestOrder) : null,
                ];
            })
            ->filter(fn (array $donor): bool => $donor['show_url'] !== null)
            ->values();

        return [
            'today' => $birthdayDonors
                ->filter(fn (array $donor): bool => $donor['is_today'])
                ->sortBy('donor_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all(),
            'upcoming' => $birthdayDonors
                ->filter(fn (array $donor): bool => ! $donor['is_today']
                    && $donor['days_until_birthday'] !== null
                    && $donor['days_until_birthday'] > 0
                    && $donor['days_until_birthday'] <= 7)
                ->sortBy('days_until_birthday')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     */
    public static function paginateCollection($items, int $perPage, string $pageName, int $page = 1, array $query = []): array
    {
        $total = $items->count();
        $currentPage = max(1, min($page, max(1, (int) ceil($total / $perPage))));
        $offset = ($currentPage - 1) * $perPage;

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items->slice($offset, $perPage)->values()->all(),
            $total,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );

        if ($query !== []) {
            $paginator->appends($query);
        }

        return AdminInertiaResources::paginated($paginator, fn (array $row) => $row, 1);
    }

    /**
     * Month selector helpers
     */
    public static function monthRange(?string $monthKey): array
    {
        $current = now()->startOfMonth();

        if (! $monthKey) {
            $start = $current;
            $end = $current->copy()->endOfMonth();

            return [
                'key' => $current->format('Y-m'),
                'label' => $current->format('F Y'),
                'start' => $start,
                'end' => $end,
            ];
        }

        try {
            $start = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
        } catch (\Throwable) {
            $start = $current;
        }

        $end = $start->copy()->endOfMonth();

        return [
            'key' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'start' => $start,
            'end' => $end,
        ];
    }

    public static function monthOptions(): array
    {
        return collect(self::monthlyTrend())
            ->map(fn (array $row) => [
                'key' => $row['key'],
                'label' => $row['label'],
            ])
            ->sortBy('key')
            ->values()
            ->all();
    }

    public static function topCausesForRange(Carbon $start, Carbon $end): array
    {
        return \App\Models\DonationItem::query()
            ->join('donation_orders', 'donation_items.donation_order_id', '=', 'donation_orders.id')
            ->join('causes', 'donation_items.cause_id', '=', 'causes.id')
            ->where('donation_orders.status', DonationOrder::STATUS_PAID)
            ->whereBetween('donation_orders.paid_at', [$start, $end])
            ->select(
                'causes.title',
                DB::raw('COUNT(DISTINCT donation_items.donation_order_id) as donations'),
                DB::raw('SUM(donation_items.amount) as amount')
            )
            ->groupBy('causes.id', 'causes.title')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($cause) => [
                'title' => $cause->title,
                'donations' => (int) $cause->donations,
                'amount' => (float) $cause->amount,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{content: string, paid_orders: int, revenue: float}>
     */
    public static function staffLeaderboard(?Carbon $start = null, ?Carbon $end = null, int $limit = 10): array
    {
        $query = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->where(function ($attributed) {
                $attributed->whereNotNull('partner_user_id')
                    ->orWhere(function ($tracked) {
                        $tracked->whereNotNull('utm_content')->where('utm_content', '!=', '');
                    });
            });

        if ($start && $end) {
            $query->whereBetween('paid_at', [$start, $end]);
        }

        // Group on the partner code when we have exact attribution so one marketer is
        // a single row rather than one row per Meta ad name.
        return $query
            ->selectRaw("COALESCE(NULLIF(partner_code, ''), utm_content) as report_code")
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('report_code')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'content' => (string) $row->report_code,
                'paid_orders' => (int) $row->paid_orders,
                'revenue' => (float) $row->revenue,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     date_label: string,
     *     from_date: string,
     *     to_date: string,
     *     total_revenue: float,
     *     total_orders: int,
     *     active_partners: int,
     *     total_target: float,
     *     total_spend: float,
     *     referrals_href: string,
     *     partners: list<array{
     *         user_id: int,
     *         name: string,
     *         code: string,
     *         paid_orders: int,
     *         revenue: float,
     *         target_amount: ?int,
     *         spend_amount: float,
     *         referrals_href: string
     *     }>
     * }
     */
    public static function dailyPartnerReferrals(?Carbon $day = null): array
    {
        $day ??= now();

        return self::partnerReferralsForRange(
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
            $day->format('d M Y'),
            [
                'duration' => 'custom',
                'from_date' => $day->toDateString(),
                'to_date' => $day->toDateString(),
            ],
            includeBudgets: false,
        );
    }

    /**
     * @return array{
     *     date_label: string,
     *     from_date: string,
     *     to_date: string,
     *     total_revenue: float,
     *     total_orders: int,
     *     active_partners: int,
     *     total_target: float,
     *     total_spend: float,
     *     referrals_href: string,
     *     partners: list<array{
     *         user_id: int,
     *         name: string,
     *         code: string,
     *         paid_orders: int,
     *         revenue: float,
     *         target_amount: ?int,
     *         spend_amount: float,
     *         referrals_href: string
     *     }>
     * }
     */
    public static function monthlyPartnerReferrals(?Carbon $reference = null): array
    {
        $reference ??= now();
        $start = $reference->copy()->startOfMonth()->startOfDay();
        $end = $reference->copy()->endOfDay();

        return self::partnerReferralsForRange(
            $start,
            $end,
            $reference->format('F Y'),
            [
                'duration' => 'this_month',
            ],
            includeBudgets: true,
            budgetReference: $reference,
        );
    }

    /**
     * @param  array<string, mixed>  $hrefParams
     * @return array{
     *     date_label: string,
     *     from_date: string,
     *     to_date: string,
     *     total_revenue: float,
     *     total_orders: int,
     *     active_partners: int,
     *     total_target: float,
     *     total_spend: float,
     *     referrals_href: string,
     *     partners: list<array{
     *         user_id: int,
     *         name: string,
     *         code: string,
     *         paid_orders: int,
     *         revenue: float,
     *         target_amount: ?int,
     *         spend_amount: float,
     *         referrals_href: string
     *     }>
     * }
     */
    public static function partnerReferralsForRange(
        Carbon $start,
        Carbon $end,
        string $dateLabel,
        array $hrefParams,
        bool $includeBudgets = false,
        ?Carbon $budgetReference = null,
    ): array {
        $users = User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'referral_code']);

        $budgetMap = $includeBudgets
            ? MarketerMonthlyBudgetService::mapForUsers($users->pluck('id')->all(), $budgetReference)
            : [];

        $partners = $users
            ->map(function (User $user) use ($start, $end, $hrefParams, $includeBudgets, $budgetMap) {
                $statsQuery = DonationOrder::query()
                    ->where('status', DonationOrder::STATUS_PAID)
                    ->whereBetween('paid_at', [$start, $end]);

                AdminStaffReferralsData::applyPartnerAttributionFilter($statsQuery, $user);

                $code = (string) $user->referral_code;
                $budget = $includeBudgets
                    ? ($budgetMap[$user->id] ?? ['target_amount' => null, 'spend_amount' => 0.0])
                    : ['target_amount' => null, 'spend_amount' => 0.0];

                return [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'code' => $code,
                    'paid_orders' => (int) (clone $statsQuery)->count(),
                    'revenue' => (float) (clone $statsQuery)->sum('total_amount'),
                    'target_amount' => $budget['target_amount'],
                    'spend_amount' => (float) $budget['spend_amount'],
                    'referrals_href' => route('admin.referrals.index', array_merge($hrefParams, [
                        'partner_user_id' => $user->id,
                    ])),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();

        // Legacy name matching can match one order to more than one partner, so the
        // period total is counted over distinct orders instead of summing partner rows.
        $totals = self::distinctPartnerOrderTotals($start, $end);

        $totalTarget = array_sum(array_map(
            static fn (array $row): float => (float) ($row['target_amount'] ?? 0),
            $partners,
        ));
        $totalSpend = array_sum(array_map(
            static fn (array $row): float => (float) ($row['spend_amount'] ?? 0),
            $partners,
        ));

        return [
            'date_label' => $dateLabel,
            'from_date' => $start->toDateString(),
            'to_date' => $end->toDateString(),
            'total_revenue' => $totals['revenue'],
            'total_orders' => $totals['orders'],
            'active_partners' => count(array_filter($partners, fn (array $row) => $row['paid_orders'] > 0)),
            'total_target' => $totalTarget,
            'total_spend' => $totalSpend,
            'referrals_href' => route('admin.referrals.index', $hrefParams),
            'partners' => $partners,
        ];
    }

    /**
     * Paid orders attributable to any registered partner, counted once per order.
     *
     * @return array{orders: int, revenue: float}
     */
    private static function distinctPartnerOrderTotals(Carbon $start, Carbon $end): array
    {
        $partners = User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->get(['id', 'name', 'referral_code']);

        if ($partners->isEmpty()) {
            return ['orders' => 0, 'revenue' => 0.0];
        }

        $query = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->where(function (Builder $builder) use ($partners) {
                foreach ($partners as $partner) {
                    $builder->orWhere(function (Builder $partnerQuery) use ($partner) {
                        AdminStaffReferralsData::applyPartnerAttributionFilter($partnerQuery, $partner);
                    });
                }
            });

        return [
            'orders' => (int) (clone $query)->count(),
            'revenue' => (float) (clone $query)->sum('total_amount'),
        ];
    }
}
