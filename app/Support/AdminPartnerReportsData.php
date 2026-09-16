<?php

namespace App\Support;

use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminPartnerReportsData
{
    /**
     * @var array<string, string>
     */
    public const DURATION_OPTIONS = [
        'today' => 'Today (daywise)',
        'yesterday' => 'Yesterday',
        'this_week' => 'This week',
        'last_week' => 'Previous week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'custom' => 'Custom range',
    ];

    /**
     * @return array{
     *     can_view_all: bool,
     *     duration: array{key: string, label: string, from_date: ?string, to_date: ?string},
     *     summary: array{total_revenue: float, total_orders: int, active_partners: int},
     *     partners: list<array{
     *         user_id: int,
     *         name: string,
     *         code: string,
     *         paid_orders: int,
     *         revenue: float,
     *         referrals_href: string
     *     }>,
     *     filter_options: array{partners: list<array{id: int, name: string, code: string}>, states: list<string>},
     *     filters: array<string, mixed>,
     *     durationOptions: array<string, string>,
     *     active_filter_count: int
     * }
     */
    public static function pageFromRequest(User $user, Request $request): array
    {
        $canViewAll = AdminStaffReferralsData::userCanViewAll($user);
        $filters = self::normalizeFilters($user, $request, $canViewAll);
        $duration = self::resolveDuration(
            $filters['duration'],
            $filters['from_date'],
            $filters['to_date'],
        );

        $partnerRows = self::partnerPerformanceRows(
            $duration['start'],
            $duration['end'],
            $filters,
            $canViewAll,
            $user,
        );

        $activePartners = count(array_filter(
            $partnerRows,
            static fn (array $row): bool => $row['paid_orders'] > 0,
        ));

        $totals = self::distinctPartnerOrderTotals(
            $duration['start'],
            $duration['end'],
            $filters,
            $canViewAll,
            $user,
        );

        return [
            'can_view_all' => $canViewAll,
            'duration' => [
                'key' => $duration['key'],
                'label' => $duration['label'],
                'from_date' => $duration['from_date'],
                'to_date' => $duration['to_date'],
            ],
            'summary' => [
                'total_revenue' => $totals['revenue'],
                'total_orders' => $totals['orders'],
                'active_partners' => $activePartners,
            ],
            'generated_at' => now()->timezone(config('app.timezone'))->format('d M Y, h:i A'),
            'partners' => $partnerRows,
            'filter_options' => [
                'partners' => self::partnerOptions($canViewAll, $user),
                'states' => self::stateOptions(),
            ],
            'filters' => [
                ...$filters,
                'duration' => $duration['key'],
                'from_date' => $duration['from_date'],
                'to_date' => $duration['to_date'],
            ],
            'durationOptions' => self::DURATION_OPTIONS,
            'active_filter_count' => self::activeFilterCount($filters),
        ];
    }

    /**
     * @return array{
     *     duration: string,
     *     from_date: ?string,
     *     to_date: ?string,
     *     state: ?string,
     *     partner_user_id: ?int,
     *     code: ?string,
     *     search: ?string
     * }
     */
    private static function normalizeFilters(User $user, Request $request, bool $canViewAll): array
    {
        $duration = (string) $request->input('duration', 'today');
        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = 'today';
        }

        $partnerUserId = $request->filled('partner_user_id') ? (int) $request->input('partner_user_id') : null;
        $partnerCode = null;

        if ($partnerUserId) {
            $partner = User::query()->find($partnerUserId);
            $partnerCode = StaffReferral::normalize($partner?->referral_code);
            if ($partnerCode === null) {
                $partnerUserId = null;
            }
        }

        $requestedCode = StaffReferral::normalize(
            is_string($request->input('code')) ? $request->input('code') : null
        );

        if ($canViewAll) {
            $code = $partnerCode ?? $requestedCode;
        } else {
            $code = StaffReferral::normalize($user->referral_code);
            $partnerUserId = null;
        }

        $state = is_string($request->input('state')) ? trim($request->input('state')) : null;
        if ($state === '') {
            $state = null;
        }

        return [
            'duration' => $duration,
            'from_date' => is_string($request->input('from_date')) ? $request->input('from_date') : null,
            'to_date' => is_string($request->input('to_date')) ? $request->input('to_date') : null,
            'state' => $state,
            'partner_user_id' => $partnerUserId,
            'code' => $code,
            'search' => self::nullableString($request->input('search')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{
     *     user_id: int,
     *     name: string,
     *     code: string,
     *     paid_orders: int,
     *     revenue: float,
     *     referrals_href: string
     * }>
     */
    private static function partnerPerformanceRows(
        ?Carbon $start,
        ?Carbon $end,
        array $filters,
        bool $canViewAll,
        User $actor,
    ): array {
        $partners = self::scopedPartners($canViewAll, $actor, $filters);

        $fromDate = $start?->toDateString();
        $toDate = $end?->toDateString();

        return $partners
            ->map(function (User $partner) use ($start, $end, $filters, $fromDate, $toDate) {
                $statsQuery = DonationOrder::query()
                    ->where('status', DonationOrder::STATUS_PAID);

                if ($start && $end) {
                    $statsQuery->whereBetween('paid_at', [$start, $end]);
                }

                AdminStaffReferralsData::applyPartnerAttributionFilter($statsQuery, $partner);
                self::applyStateAndSearchFilters($statsQuery, $filters);

                $code = (string) $partner->referral_code;

                $detailQuery = [
                    'partner_user_id' => $partner->id,
                    'duration' => ($fromDate && $toDate) ? 'custom' : 'this_month',
                ];

                if ($fromDate && $toDate) {
                    $detailQuery['from_date'] = $fromDate;
                    $detailQuery['to_date'] = $toDate;
                }

                return [
                    'user_id' => $partner->id,
                    'name' => $partner->name,
                    'code' => $code,
                    'paid_orders' => (int) (clone $statsQuery)->count(),
                    'revenue' => (float) (clone $statsQuery)->sum('total_amount'),
                    'referrals_href' => route('admin.referrals.index', $detailQuery),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{orders: int, revenue: float}
     */
    private static function distinctPartnerOrderTotals(
        ?Carbon $start,
        ?Carbon $end,
        array $filters,
        bool $canViewAll,
        User $actor,
    ): array {
        $partners = self::scopedPartners($canViewAll, $actor, $filters);

        if ($partners->isEmpty()) {
            return ['orders' => 0, 'revenue' => 0.0];
        }

        $query = DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID);

        if ($start && $end) {
            $query->whereBetween('paid_at', [$start, $end]);
        }

        self::applyStateAndSearchFilters($query, $filters);

        $query->where(function (Builder $builder) use ($partners) {
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

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, User>
     */
    private static function scopedPartners(bool $canViewAll, User $actor, array $filters): Collection
    {
        $query = User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->orderBy('name');

        if (! $canViewAll) {
            $query->whereKey($actor->id);
        } elseif (filled($filters['partner_user_id'] ?? null)) {
            $query->whereKey((int) $filters['partner_user_id']);
        } elseif (filled($filters['code'] ?? null)) {
            $normalized = StaffReferral::normalize((string) $filters['code']);
            if ($normalized !== null) {
                $query->whereRaw('LOWER(referral_code) = ?', [$normalized]);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (filled($filters['search'] ?? null) && $canViewAll && blank($filters['partner_user_id'] ?? null) && blank($filters['code'] ?? null)) {
            $term = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('referral_code', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        return $query->get(['id', 'name', 'referral_code', 'email']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function applyStateAndSearchFilters(Builder $query, array $filters): void
    {
        if (filled($filters['state'] ?? null)) {
            $query->where('state', $filters['state']);
        }
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    private static function partnerOptions(bool $canViewAll, User $actor): array
    {
        $query = User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->orderBy('name');

        if (! $canViewAll) {
            $query->whereKey($actor->id);
        }

        return $query
            ->get(['id', 'name', 'referral_code'])
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'code' => (string) $user->referral_code,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function stateOptions(): array
    {
        return DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state')
            ->map(static fn ($state): string => (string) $state)
            ->values()
            ->all();
    }

    /**
     * @return array{start: ?Carbon, end: ?Carbon, key: string, label: string, from_date: ?string, to_date: ?string}
     */
    private static function resolveDuration(string $duration, ?string $fromDate = null, ?string $toDate = null): array
    {
        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = 'today';
        }

        $now = now();

        if ($duration === 'custom') {
            $start = filled($fromDate) ? Carbon::parse($fromDate)->startOfDay() : $now->copy()->startOfMonth();
            $end = filled($toDate) ? Carbon::parse($toDate)->endOfDay() : $now->copy()->endOfDay();

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            return [
                'key' => 'custom',
                'label' => $start->format('d M Y').' – '.$end->format('d M Y'),
                'start' => $start,
                'end' => $end,
                'from_date' => $start->toDateString(),
                'to_date' => $end->toDateString(),
            ];
        }

        if ($calendar = PeriodRange::forKey($duration)) {
            return [
                'key' => $duration,
                'label' => self::DURATION_OPTIONS[$duration],
                'start' => $calendar['start'],
                'end' => $calendar['end'],
                'from_date' => $calendar['start']->toDateString(),
                'to_date' => $calendar['end']->toDateString(),
            ];
        }

        return [
            'key' => 'today',
            'label' => self::DURATION_OPTIONS['today'],
            'start' => $now->copy()->startOfDay(),
            'end' => $now->copy()->endOfDay(),
            'from_date' => $now->toDateString(),
            'to_date' => $now->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function activeFilterCount(array $filters): int
    {
        $count = 0;

        if (($filters['duration'] ?? 'today') !== 'today') {
            $count++;
        }
        if (filled($filters['state'] ?? null)) {
            $count++;
        }
        if (filled($filters['partner_user_id'] ?? null) || filled($filters['code'] ?? null)) {
            $count++;
        }
        if (filled($filters['search'] ?? null)) {
            $count++;
        }

        return $count;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
