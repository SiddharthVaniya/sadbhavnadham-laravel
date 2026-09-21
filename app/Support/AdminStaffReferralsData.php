<?php

namespace App\Support;

use App\Models\Cause;
use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminStaffReferralsData
{
    public const PERMISSION = 'view staff referrals';

    /**
     * @var array<string, string>
     */
    public const DURATION_OPTIONS = [
        'yesterday' => 'Yesterday',
        'this_week' => 'This week',
        'last_week' => 'Previous week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_fy' => 'This financial year',
        'custom' => 'Custom range',
        'all' => 'All time',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_OPTIONS = [
        '' => 'All statuses',
        'paid' => 'Paid only',
        'pending' => 'Pending only',
        'failed' => 'Failed only',
    ];

    /**
     * @var array<string, string>
     */
    public const MATCH_OPTIONS = [
        '' => 'All tracking codes',
        'partners' => 'Registered partners only',
        'unmatched' => 'Unmatched codes only',
    ];

    public static function userCanViewAll(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can(DonationVisibility::VIEW_ALL)
            || $user->can('view analytics')
            || $user->can('manage users');
    }

    /**
     * @return array{
     *     code: ?string,
     *     partner_user_id: ?int,
     *     can_view_all: bool,
     *     has_own_code: bool,
     *     own_code: ?string,
     *     own_share_url: ?string,
     *     meta_ad_parameters: ?string,
     *     meta_ad_url: ?string,
     *     duration: array{key: string, label: string, from_date: ?string, to_date: ?string},
     *     summary: array{paid_orders: int, revenue: float, pending_orders: int, pending_amount: float, failed_orders: int, failed_amount: float},
     *     leaderboard: list<array{code: string, name: ?string, paid_orders: int, revenue: float, is_partner: bool}>,
     *     donations: array<string, mixed>,
     *     filter_options: array<string, mixed>,
     *     filters: array<string, mixed>,
     *     active_filter_count: int
     * }
     */
    public static function pageFromRequest(User $user, Request $request): array
    {
        $canViewAll = self::userCanViewAll($user);
        $filters = self::normalizeFilters($user, $request, $canViewAll);
        $duration = self::resolveDuration(
            $filters['duration'],
            $filters['from_date'],
            $filters['to_date'],
        );

        $ownCode = StaffReferral::normalize($user->referral_code);
        $partnerCodes = self::registeredPartnerCodes();

        $summaryQuery = self::filteredQuery($duration['start'], $duration['end'], $filters, $canViewAll);
        $donationsQuery = self::filteredQuery($duration['start'], $duration['end'], $filters, $canViewAll)
            ->with(['items.causeModel'])
            ->latest('paid_at')
            ->latest('id');

        $paidSummary = (clone $summaryQuery)
            ->where('status', DonationOrder::STATUS_PAID)
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        $pendingSummary = (clone $summaryQuery)
            ->where('status', DonationOrder::STATUS_PENDING)
            ->selectRaw('COUNT(*) as pending_orders')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as pending_amount')
            ->first();

        $failedSummary = (clone $summaryQuery)
            ->where('status', DonationOrder::STATUS_FAILED)
            ->selectRaw('COUNT(*) as failed_orders')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as failed_amount')
            ->first();

        /** @var LengthAwarePaginator $paginator */
        $paginator = $donationsQuery->paginate(AdminInertiaResources::LIST_PER_PAGE)->withQueryString();

        return [
            'code' => $filters['code'],
            'partner_user_id' => $filters['partner_user_id'],
            'can_view_all' => $canViewAll,
            'has_own_code' => $ownCode !== null,
            'own_code' => $ownCode,
            'own_share_url' => $ownCode !== null
                ? StaffReferral::trackedShareUrl(DonationPublicFrontend::homeUrl(), $ownCode)
                : null,
            'meta_ad_parameters' => $ownCode !== null ? StaffReferral::metaAdParameters($ownCode, $user->name) : null,
            'meta_ad_url' => $ownCode !== null
                ? StaffReferral::metaAdUrl(DonationPublicFrontend::homeUrl(), $ownCode, $user->name)
                : null,
            'duration' => [
                'key' => $duration['key'],
                'label' => $duration['label'],
                'from_date' => $duration['from_date'],
                'to_date' => $duration['to_date'],
            ],
            'summary' => [
                'paid_orders' => (int) ($paidSummary->paid_orders ?? 0),
                'revenue' => (float) ($paidSummary->revenue ?? 0),
                'pending_orders' => (int) ($pendingSummary->pending_orders ?? 0),
                'pending_amount' => (float) ($pendingSummary->pending_amount ?? 0),
                'failed_orders' => (int) ($failedSummary->failed_orders ?? 0),
                'failed_amount' => (float) ($failedSummary->failed_amount ?? 0),
            ],
            'leaderboard' => $canViewAll
                ? self::leaderboard($duration['start'], $duration['end'], $filters, $partnerCodes)
                : [],
            'donations' => self::paginatedAttributedDonations($paginator),
            'filter_options' => self::filterOptions($canViewAll),
            'filters' => [
                ...$filters,
                'duration' => $duration['key'],
                'from_date' => $duration['from_date'],
                'to_date' => $duration['to_date'],
            ],
            'active_filter_count' => self::activeFilterCount($filters),
            'durationOptions' => self::DURATION_OPTIONS,
            'statusOptions' => self::STATUS_OPTIONS,
            'matchOptions' => self::MATCH_OPTIONS,
        ];
    }

    /**
     * @return array{
     *     duration: string,
     *     from_date: ?string,
     *     to_date: ?string,
     *     code: ?string,
     *     partner_user_id: ?int,
     *     cause_id: ?int,
     *     status: string,
     *     utm_campaign: ?string,
     *     utm_source: ?string,
     *     match: string,
     *     search: ?string,
     *     content_search: ?string
     * }
     */
    public static function normalizeFilters(User $user, Request $request, bool $canViewAll): array
    {
        $duration = (string) $request->input('duration', 'this_month');
        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = 'this_month';
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

        // Free-form tracking codes (campaign titles) keep raw value when not a slug-normalized code.
        $rawCode = is_string($request->input('code')) ? trim($request->input('code')) : null;
        if ($rawCode === '') {
            $rawCode = null;
        }

        if ($canViewAll) {
            $code = $partnerCode ?? $rawCode;
        } else {
            $code = StaffReferral::normalize($user->referral_code);
            $partnerUserId = null;
        }

        $status = (string) $request->input('status', '');
        if (! array_key_exists($status, self::STATUS_OPTIONS)) {
            $status = '';
        }

        $match = (string) $request->input('match', '');
        if (! $canViewAll || ! array_key_exists($match, self::MATCH_OPTIONS)) {
            $match = '';
        }

        $causeId = $request->filled('cause_id') ? (int) $request->input('cause_id') : null;
        if ($causeId && ! Cause::query()->whereKey($causeId)->exists()) {
            $causeId = null;
        }

        return [
            'duration' => $duration,
            'from_date' => is_string($request->input('from_date')) ? $request->input('from_date') : null,
            'to_date' => is_string($request->input('to_date')) ? $request->input('to_date') : null,
            'code' => $code,
            'partner_user_id' => $partnerUserId,
            'cause_id' => $causeId,
            'status' => $status,
            'utm_campaign' => self::nullableString($request->input('utm_campaign')),
            'utm_source' => self::nullableString($request->input('utm_source')),
            'match' => $match,
            'search' => self::nullableString($request->input('search')),
            'content_search' => self::nullableString($request->input('content_search')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function filteredQuery(
        ?Carbon $start,
        ?Carbon $end,
        array $filters,
        bool $canViewAll,
    ): Builder {
        $query = self::baseQuery($start, $end);

        if (filled($filters['partner_user_id'] ?? null)) {
            $partner = User::query()->find((int) $filters['partner_user_id']);

            if ($partner) {
                self::applyPartnerAttributionFilter($query, $partner);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif (filled($filters['code'] ?? null)) {
            $code = (string) $filters['code'];
            $partner = StaffReferral::findUserByCode($code);

            if ($partner !== null && StaffReferral::normalize($code) === $code) {
                self::applyPartnerAttributionFilter($query, $partner);
            } else {
                $query->where('utm_content', $code);
            }
        } elseif (! $canViewAll) {
            $query->whereRaw('1 = 0');
        } else {
            // A sid-only ad link carries no utm_content, so key off either dimension.
            $query->where(function (Builder $attributed) {
                $attributed->whereNotNull('partner_user_id')
                    ->orWhere(function (Builder $tracked) {
                        $tracked->whereNotNull('utm_content')->where('utm_content', '!=', '');
                    });
            });
        }

        if (filled($filters['content_search'] ?? null) && $canViewAll && blank($filters['code'] ?? null)) {
            $term = (string) $filters['content_search'];
            $query->where('utm_content', 'like', '%'.$term.'%');
        }

        if (filled($filters['utm_campaign'] ?? null)) {
            $query->where('utm_campaign', $filters['utm_campaign']);
        }

        if (filled($filters['utm_source'] ?? null)) {
            $query->where('utm_source', $filters['utm_source']);
        }

        if (filled($filters['cause_id'] ?? null)) {
            $causeId = (int) $filters['cause_id'];
            $query->whereHas('items', fn (Builder $items) => $items->where('cause_id', $causeId));
        }

        if (($filters['status'] ?? '') === 'paid') {
            $query->where('status', DonationOrder::STATUS_PAID);
        } elseif (($filters['status'] ?? '') === 'pending') {
            $query->where('status', DonationOrder::STATUS_PENDING);
        } elseif (($filters['status'] ?? '') === 'failed') {
            $query->where('status', DonationOrder::STATUS_FAILED);
        }

        if ($canViewAll && ($filters['match'] ?? '') === 'partners') {
            $partners = User::query()
                ->whereNotNull('referral_code')
                ->where('referral_code', '!=', '')
                ->get(['id', 'name', 'referral_code']);

            if ($partners->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function (Builder $builder) use ($partners) {
                    foreach ($partners as $partner) {
                        $builder->orWhere(function (Builder $partnerQuery) use ($partner) {
                            self::applyPartnerAttributionFilter($partnerQuery, $partner);
                        });
                    }
                });
            }
        } elseif ($canViewAll && ($filters['match'] ?? '') === 'unmatched') {
            $partners = User::query()
                ->whereNotNull('referral_code')
                ->where('referral_code', '!=', '')
                ->get(['id', 'name', 'referral_code']);

            foreach ($partners as $partner) {
                $query->whereNot(function (Builder $builder) use ($partner) {
                    self::applyPartnerAttributionFilter($builder, $partner);
                });
            }
        }

        if (filled($filters['search'] ?? null)) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('donor_name', 'like', "%{$search}%")
                    ->orWhere('donor_email', 'like', "%{$search}%")
                    ->orWhere('donor_phone', 'like', "%{$search}%")
                    ->orWhere('provider_payment_id', 'like', "%{$search}%")
                    ->orWhere('provider_order_id', 'like', "%{$search}%")
                    ->orWhere('receipt_number', 'like', "%{$search}%")
                    ->orWhere('utm_content', 'like', "%{$search}%")
                    ->orWhere('utm_campaign', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $partnerCodes
     * @return list<array{code: string, name: ?string, paid_orders: int, revenue: float, is_partner: bool}>
     */
    public static function leaderboard(?Carbon $start, ?Carbon $end, array $filters, array $partnerCodes, int $limit = 50): array
    {
        $leaderboardFilters = $filters;
        // Leaderboard always groups paid attribution; ignore status filter for paid ranking.
        $leaderboardFilters['status'] = 'paid';

        // Exactly attributed orders collapse under the partner code. Grouping on
        // utm_content alone would scatter one marketer across every Meta ad name.
        $rows = self::filteredQuery($start, $end, $leaderboardFilters, canViewAll: true)
            ->selectRaw("COALESCE(NULLIF(partner_code, ''), utm_content) as report_code")
            ->selectRaw('MAX(partner_user_id) as partner_user_id')
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('SUM(total_amount) as revenue')
            ->groupBy('report_code')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $names = User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->get(['id', 'name', 'referral_code']);

        $namesByCode = $names->keyBy(fn (User $user) => (string) StaffReferral::normalize($user->referral_code));
        $namesById = $names->keyBy('id');

        $partnerLookup = array_fill_keys($partnerCodes, true);

        return $rows
            ->map(function ($row) use ($names, $namesByCode, $namesById, $partnerLookup) {
                $code = (string) $row->report_code;
                $normalized = StaffReferral::normalize($code) ?? strtolower($code);
                $exactPartner = $row->partner_user_id ? $namesById->get((int) $row->partner_user_id) : null;
                $matchedPartner = $exactPartner
                    ?? $names->first(fn (User $user) => self::orderAttributionMatchesPartner($code, null, $user));

                return [
                    'code' => $code,
                    'name' => $namesByCode->get($normalized)?->name ?? $matchedPartner?->name,
                    'paid_orders' => (int) $row->paid_orders,
                    'revenue' => (float) $row->revenue,
                    'is_partner' => $exactPartner !== null
                        || isset($partnerLookup[$code])
                        || isset($partnerLookup[$normalized])
                        || $matchedPartner !== null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     partners: list<array{id: int, name: string, code: string, label: string, meta_ad_parameters: string}>,
     *     tracking_codes: list<string>,
     *     campaigns: list<string>,
     *     sources: list<string>,
     *     causes: list<array{id: int, title: string}>
     * }
     */
    public static function filterOptions(bool $canViewAll): array
    {
        $partners = [];

        if ($canViewAll) {
            $partners = User::query()
                ->whereNotNull('referral_code')
                ->where('referral_code', '!=', '')
                ->orderBy('name')
                ->get(['id', 'name', 'referral_code'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'code' => (string) $user->referral_code,
                    'label' => $user->name.' · '.$user->referral_code,
                    'meta_ad_parameters' => StaffReferral::metaAdParameters($user->referral_code, $user->name),
                ])
                ->values()
                ->all();
        }

        return [
            'partners' => $partners,
            'tracking_codes' => DonationOrder::query()
                ->whereNotNull('utm_content')
                ->where('utm_content', '!=', '')
                ->distinct()
                ->orderBy('utm_content')
                ->limit(200)
                ->pluck('utm_content')
                ->values()
                ->all(),
            'campaigns' => DonationOrder::query()
                ->whereNotNull('utm_campaign')
                ->where('utm_campaign', '!=', '')
                ->distinct()
                ->orderBy('utm_campaign')
                ->limit(150)
                ->pluck('utm_campaign')
                ->values()
                ->all(),
            'sources' => DonationOrder::query()
                ->whereNotNull('utm_source')
                ->where('utm_source', '!=', '')
                ->distinct()
                ->orderBy('utm_source')
                ->limit(50)
                ->pluck('utm_source')
                ->values()
                ->all(),
            'causes' => Cause::query()
                ->orderBy('title')
                ->get(['id', 'title'])
                ->map(fn (Cause $cause) => [
                    'id' => $cause->id,
                    'title' => $cause->title,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{start: ?Carbon, end: ?Carbon, key: string, label: string, from_date: ?string, to_date: ?string}
     */
    public static function resolveDuration(string $duration, ?string $fromDate = null, ?string $toDate = null): array
    {
        if (! array_key_exists($duration, self::DURATION_OPTIONS)) {
            $duration = 'this_month';
        }

        if ($duration === 'custom') {
            return self::customRange($fromDate, $toDate);
        }

        $now = now();

        if ($calendar = PeriodRange::forKey($duration)) {
            return [
                'key' => $duration,
                'label' => self::DURATION_OPTIONS[$duration],
                'start' => $calendar['start'],
                'end' => $calendar['end'],
                'from_date' => null,
                'to_date' => null,
            ];
        }

        return match ($duration) {
            'this_fy' => self::financialYearWindow($now),
            'all' => [
                'key' => 'all',
                'label' => self::DURATION_OPTIONS['all'],
                'start' => null,
                'end' => null,
                'from_date' => null,
                'to_date' => null,
            ],
            default => [
                'key' => 'this_month',
                'label' => self::DURATION_OPTIONS['this_month'],
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
                'from_date' => null,
                'to_date' => null,
            ],
        };
    }

    public static function resolveScopeCode(User $user, ?string $requestedCode): ?string
    {
        $ownCode = StaffReferral::normalize($user->referral_code);

        if (! self::userCanViewAll($user)) {
            return $ownCode;
        }

        return StaffReferral::normalize($requestedCode);
    }

    public static function applyPartnerAttributionFilter(Builder $query, User $partner): void
    {
        $code = StaffReferral::normalize($partner->referral_code);
        $name = trim($partner->name);

        $query->where(function (Builder $builder) use ($partner, $code, $name) {
            // Exact attribution (sid / staff code resolved at capture time).
            // whereNotNull keeps this branch false (never NULL) so `whereNot(...)` inversions work.
            $builder->where(function (Builder $exact) use ($partner) {
                $exact->whereNotNull('partner_user_id')
                    ->where('partner_user_id', $partner->id);
            });

            // Legacy rows captured before sid existed still rely on name/code matching.
            $builder->orWhere(function (Builder $legacy) use ($code, $name) {
                $legacy->whereNull('partner_user_id');

                $legacy->where(function (Builder $inner) use ($code, $name) {
                    $hasMatch = false;

                    if ($code !== null) {
                        $inner->where('utm_content', $code);
                        $hasMatch = true;
                    }

                    if ($name !== '') {
                        $like = '%'.addcslashes($name, '%_\\').'%';
                        $nameMatcher = function (Builder $nameQuery) use ($like) {
                            $nameQuery->where('utm_content', 'like', $like)
                                ->orWhere('utm_campaign', 'like', $like);
                        };

                        if ($hasMatch) {
                            $inner->orWhere($nameMatcher);
                        } else {
                            $inner->where($nameMatcher);
                        }

                        $hasMatch = true;
                    }

                    if (! $hasMatch) {
                        $inner->whereRaw('1 = 0');
                    }
                });
            });
        });
    }

    /**
     * Partner attribution table: "Code" must show the tracking/partner code
     * (sid), not the Meta ad name stored in utm_content.
     *
     * @return array{data: list<array<string, mixed>>, links: mixed, meta: array<string, mixed>}
     */
    private static function paginatedAttributedDonations(LengthAwarePaginator $paginator): array
    {
        $partnerCodesByUserId = User::query()
            ->whereIn(
                'id',
                collect($paginator->items())
                    ->pluck('partner_user_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            )
            ->get(['id', 'referral_code'])
            ->mapWithKeys(fn (User $user) => [
                $user->id => StaffReferral::normalize($user->referral_code),
            ]);

        return [
            'data' => collect($paginator->items())
                ->flatMap(function (DonationOrder $order) use ($partnerCodesByUserId) {
                    return collect(AdminInertiaData::donationTableRows($order))
                        ->map(function (array $row) use ($order, $partnerCodesByUserId) {
                            $trackingCode = filled($order->partner_code)
                                ? (string) $order->partner_code
                                : (
                                    $order->partner_user_id
                                        ? ($partnerCodesByUserId[$order->partner_user_id] ?? null)
                                        : null
                                );

                            // Keep Meta ad name available; Code column reads utm_content.
                            $row['ad_name'] = filled($order->utm_content) ? (string) $order->utm_content : null;
                            $row['partner_code'] = $trackingCode;
                            $row['utm_content'] = $trackingCode
                                ?? (filled($order->utm_content) ? (string) $order->utm_content : null);

                            return $row;
                        });
                })
                ->values()
                ->all(),
            'links' => $paginator->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public static function orderAttributionMatchesPartner(?string $utmContent, ?string $utmCampaign, User $partner): bool
    {
        $code = StaffReferral::normalize($partner->referral_code);
        $content = trim((string) $utmContent);
        $campaign = trim((string) $utmCampaign);

        if ($code !== null && strcasecmp($content, $code) === 0) {
            return true;
        }

        $name = trim($partner->name);

        if ($name === '') {
            return false;
        }

        return stripos($content, $name) !== false
            || ($campaign !== '' && stripos($campaign, $name) !== false);
    }

    /**
     * @return list<string>
     */
    private static function registeredPartnerCodes(): array
    {
        return User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->pluck('referral_code')
            ->flatMap(function ($code) {
                $raw = trim((string) $code);
                $normalized = StaffReferral::normalize($raw);

                return array_values(array_filter([$raw, $normalized]));
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function activeFilterCount(array $filters): int
    {
        $count = 0;

        foreach (['code', 'partner_user_id', 'cause_id', 'status', 'utm_campaign', 'utm_source', 'match', 'search', 'content_search'] as $key) {
            if (filled($filters[$key] ?? null)) {
                $count++;
            }
        }

        if (($filters['duration'] ?? '') === 'custom') {
            $count++;
        }

        return $count;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function baseQuery(?Carbon $start, ?Carbon $end): Builder
    {
        $query = DonationOrder::query();

        if ($start && $end) {
            $query->where(function (Builder $builder) use ($start, $end) {
                $builder->whereBetween('paid_at', [$start, $end])
                    ->orWhere(function (Builder $pending) use ($start, $end) {
                        $pending->whereNull('paid_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });
        }

        return $query;
    }

    /**
     * @return array{key: string, label: string, start: Carbon, end: Carbon, from_date: string, to_date: string}
     */
    private static function customRange(?string $fromDate, ?string $toDate): array
    {
        $start = filled($fromDate)
            ? Carbon::parse($fromDate)->startOfDay()
            : now()->copy()->startOfMonth()->startOfDay();
        $end = filled($toDate)
            ? Carbon::parse($toDate)->endOfDay()
            : now()->copy()->endOfDay();

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

    /**
     * @return array{key: string, label: string, start: Carbon, end: Carbon, from_date: null, to_date: null}
     */
    private static function financialYearWindow(Carbon $now): array
    {
        $start = $now->month >= 4
            ? $now->copy()->month(4)->startOfMonth()->startOfDay()
            : $now->copy()->subYear()->month(4)->startOfMonth()->startOfDay();

        $end = $start->copy()->addYear()->subDay()->endOfDay();

        return [
            'key' => 'this_fy',
            'label' => self::DURATION_OPTIONS['this_fy'],
            'start' => $start,
            'end' => $end,
            'from_date' => null,
            'to_date' => null,
        ];
    }
}
