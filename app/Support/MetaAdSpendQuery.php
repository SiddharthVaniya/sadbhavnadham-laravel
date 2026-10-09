<?php

namespace App\Support;

use App\Models\MetaAdAccount;
use App\Models\MetaAdSpendDaily;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MetaAdSpendQuery
{
    /**
     * @return array{
     *     filters: array<string, string>,
     *     filter_options: array<string, mixed>,
     *     analytics: array<string, mixed>,
     *     rows: LengthAwarePaginator,
     *     unmatched_count: int,
     *     last_synced_at: ?string
     * }
     */
    public static function adminIndex(Request $request): array
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $filters = self::parseFilters($request, includeMarketer: true, includeMatch: true);
        $query = self::filteredQuery($filters);
        $analyticsQuery = self::filteredQuery($filters);

        $unmatchedCount = (clone $query)
            ->where('matched_via', MetaAdSpendDaily::MATCHED_VIA_UNMATCHED)
            ->count();

        $rows = $query
            ->with(['user:id,name,referral_code', 'account:id,label,app_id'])
            ->orderByDesc('spend_date')
            ->orderByDesc('spend_amount')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString()
            ->through(fn (MetaAdSpendDaily $row) => self::serializeRow($row));

        return [
            'filters' => $filters,
            'filter_options' => self::filterOptions(null),
            'analytics' => self::buildAnalytics($analyticsQuery, includeMarketerBreakdown: true),
            'rows' => $rows,
            'unmatched_count' => $unmatchedCount,
            'last_synced_at' => self::lastSyncedAt($tz),
        ];
    }

    /**
     * Last 7 days spend overview for the Meta Accounts tab.
     *
     * @return array<string, mixed>
     */
    public static function accountsOverviewAnalytics(): array
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $from = now($tz)->subDays(6)->startOfDay()->toDateString();
        $to = now($tz)->toDateString();

        $query = MetaAdSpendDaily::query()
            ->whereDate('spend_date', '>=', $from)
            ->whereDate('spend_date', '<=', $to);

        return self::buildAnalytics($query, includeMarketerBreakdown: true);
    }

    /**
     * @return array{
     *     filters: array<string, string>,
     *     filter_options: array<string, mixed>,
     *     analytics: array<string, mixed>,
     *     rows: LengthAwarePaginator,
     *     last_synced_at: ?string
     * }
     */
    public static function marketerIndex(Request $request, int $userId): array
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $filters = self::parseFilters($request, includeMarketer: false, includeMatch: false);
        $query = self::filteredQuery($filters, $userId);
        $analyticsQuery = self::filteredQuery($filters, $userId);

        $rows = $query
            ->with(['account:id,label,app_id'])
            ->orderByDesc('spend_date')
            ->orderByDesc('spend_amount')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString()
            ->through(fn (MetaAdSpendDaily $row) => self::serializeRow($row, false));

        return [
            'filters' => $filters,
            'filter_options' => self::filterOptions($userId),
            'analytics' => self::buildAnalytics($analyticsQuery, includeMarketerBreakdown: false),
            'rows' => $rows,
            'last_synced_at' => self::lastSyncedAt($tz),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function parseFilters(Request $request, bool $includeMarketer, bool $includeMatch): array
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $today = now($tz)->toDateString();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'meta_ad_account_id' => trim((string) $request->input('meta_ad_account_id', '')),
            'app_id' => trim((string) $request->input('app_id', '')),
            'from_date' => trim((string) $request->input('from_date', $today)),
            'to_date' => trim((string) $request->input('to_date', $today)),
            'campaign' => trim((string) $request->input('campaign', '')),
            'adset' => trim((string) $request->input('adset', '')),
            'theme' => trim((string) $request->input('theme', '')),
            'cause' => trim((string) $request->input('cause', '')),
        ];

        if ($includeMarketer) {
            $filters['user_id'] = trim((string) $request->input('user_id', ''));
        }

        if ($includeMatch) {
            $filters['match'] = trim((string) $request->input('match', 'all'));
            if (! in_array($filters['match'], ['all', 'matched', 'unmatched'], true)) {
                $filters['match'] = 'all';
            }
        }

        if ($filters['from_date'] === '') {
            $filters['from_date'] = $today;
        }

        if ($filters['to_date'] === '') {
            $filters['to_date'] = $today;
        }

        return $filters;
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<MetaAdSpendDaily>
     */
    private static function filteredQuery(array $filters, ?int $userId = null): Builder
    {
        $query = MetaAdSpendDaily::query()
            ->whereDate('spend_date', '>=', $filters['from_date'])
            ->whereDate('spend_date', '<=', $filters['to_date']);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        MarketerNameMatcher::applySmartSearch($query, $filters['q'] ?? '');

        if (($filters['user_id'] ?? '') !== '') {
            MarketerNameMatcher::applyMarketerFilter($query, $filters['user_id']);
        }

        if (($filters['meta_ad_account_id'] ?? '') !== '') {
            $query->where('meta_ad_account_id', (int) $filters['meta_ad_account_id']);
        }

        if (($filters['app_id'] ?? '') !== '') {
            $accountIds = MetaAdAccount::query()
                ->where('app_id', $filters['app_id'])
                ->pluck('id');
            $query->whereIn('meta_ad_account_id', $accountIds);
        }

        if (($filters['campaign'] ?? '') !== '') {
            $query->where('campaign_name', $filters['campaign']);
        }

        if (($filters['adset'] ?? '') !== '') {
            $query->where('adset_name', $filters['adset']);
        }

        if (($filters['theme'] ?? '') !== '') {
            self::applyPipeSegmentFilter($query, $filters['theme']);
        }

        if (($filters['cause'] ?? '') !== '') {
            self::applyPipeSegmentFilter($query, $filters['cause']);
        }

        if (($filters['match'] ?? 'all') === 'matched') {
            $query->where('matched_via', MetaAdSpendDaily::MATCHED_VIA_AD_NAME_PREFIX);
        } elseif (($filters['match'] ?? 'all') === 'unmatched') {
            $query->where('matched_via', MetaAdSpendDaily::MATCHED_VIA_UNMATCHED);
        }

        return $query;
    }

    /**
     * Match a pipe segment (theme/cause) inside ad names like
     * "Ashvini | 09/10 | Brand | Theme | Cause". Uses LIKE for MySQL + SQLite.
     *
     * @param  Builder<MetaAdSpendDaily>  $query
     */
    private static function applyPipeSegmentFilter(Builder $query, string $value): void
    {
        $escaped = addcslashes($value, '%_\\');

        $query->where(function (Builder $builder) use ($escaped): void {
            $builder->where('ad_name', 'like', '%| '.$escaped.' |%')
                ->orWhere('ad_name', 'like', '%| '.$escaped)
                ->orWhere('ad_name', 'like', $escaped.' |%')
                ->orWhere('campaign_name', 'like', '%'.$escaped.'%');
        });
    }

    /**
     * @return array{
     *     accounts: list<array{id: int, label: string, app_id: string}>,
     *     app_ids: list<array{value: string, label: string}>,
     *     campaigns: list<string>,
     *     adsets: list<string>,
     *     themes: list<string>,
     *     causes: list<string>,
     *     marketers: list<array{id: int, name: string, code: string}>
     * }
     */
    private static function filterOptions(?int $userId): array
    {
        $spendScope = MetaAdSpendDaily::query();
        if ($userId !== null) {
            $spendScope->where('user_id', $userId);
        }

        $accountIds = (clone $spendScope)->distinct()->pluck('meta_ad_account_id')->filter()->all();

        $accounts = MetaAdAccount::query()
            ->when($accountIds !== [], fn ($q) => $q->whereIn('id', $accountIds))
            ->when($accountIds === [] && $userId !== null, fn ($q) => $q->whereRaw('0 = 1'))
            ->when($userId === null, fn ($q) => $q->orderBy('label'))
            ->orderBy('label')
            ->get(['id', 'label', 'app_id'])
            ->map(fn (MetaAdAccount $a) => [
                'id' => $a->id,
                'label' => $a->label,
                'app_id' => (string) $a->app_id,
            ])
            ->values()
            ->all();

        // Admin: always list all credential accounts for filtering even before sync.
        if ($userId === null) {
            $accounts = MetaAdAccount::query()
                ->orderBy('label')
                ->get(['id', 'label', 'app_id'])
                ->map(fn (MetaAdAccount $a) => [
                    'id' => $a->id,
                    'label' => $a->label,
                    'app_id' => (string) $a->app_id,
                ])
                ->values()
                ->all();
        }

        $appIds = collect($accounts)
            ->pluck('app_id')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $appId) use ($accounts) {
                $names = collect($accounts)
                    ->where('app_id', $appId)
                    ->pluck('label')
                    ->unique()
                    ->implode(', ');

                return [
                    'value' => $appId,
                    'label' => $names !== '' ? "{$names} ({$appId})" : $appId,
                ];
            })
            ->all();

        $campaigns = (clone $spendScope)
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->distinct()
            ->orderBy('campaign_name')
            ->pluck('campaign_name')
            ->values()
            ->all();

        $adsets = (clone $spendScope)
            ->whereNotNull('adset_name')
            ->where('adset_name', '!=', '')
            ->distinct()
            ->orderBy('adset_name')
            ->pluck('adset_name')
            ->values()
            ->all();

        $pipe = self::pipeSegmentOptions($spendScope);

        return [
            'accounts' => $accounts,
            'app_ids' => $appIds,
            'campaigns' => $campaigns,
            'adsets' => $adsets,
            'themes' => $pipe['themes'],
            'causes' => $pipe['causes'],
            'marketers' => $userId === null
                ? MarketerNameMatcher::marketerOptions()->values()->all()
                : [],
        ];
    }

    /**
     * @param  Builder<MetaAdSpendDaily>  $spendScope
     * @return array{themes: list<string>, causes: list<string>}
     */
    private static function pipeSegmentOptions(Builder $spendScope): array
    {
        $themes = [];
        $causes = [];

        (clone $spendScope)
            ->whereNotNull('ad_name')
            ->where('ad_name', '!=', '')
            ->distinct()
            ->limit(2000)
            ->pluck('ad_name')
            ->each(function (?string $name) use (&$themes, &$causes): void {
                $segments = MarketerNameMatcher::parsePipeSegments($name);
                if ($segments['theme']) {
                    $themes[$segments['theme']] = true;
                }
                if ($segments['cause']) {
                    $causes[$segments['cause']] = true;
                }
            });

        $sort = fn (array $map): array => collect(array_keys($map))->sort()->values()->all();

        return [
            'themes' => $sort($themes),
            'causes' => $sort($causes),
        ];
    }

    /**
     * @param  Builder<MetaAdSpendDaily>  $query
     * @return array{
     *     totals: array{spend: float, ads: int, campaigns: int, accounts: int, days: int},
     *     by_day: list<array{date: string, spend: float}>,
     *     by_account: list<array{name: string, spend: float, percentage: float}>,
     *     by_campaign: list<array{name: string, spend: float, percentage: float}>,
     *     by_adset: list<array{name: string, spend: float, percentage: float}>,
     *     chart_labels: list<string>,
     *     chart_series: list<array{name: string, data: list<float>}>
     * }
     */
    private static function buildAnalytics(Builder $query, bool $includeMarketerBreakdown = false): array
    {
        $rows = (clone $query)
            ->with(['account:id,label,app_id', 'user:id,name'])
            ->get([
                'id',
                'user_id',
                'spend_date',
                'spend_amount',
                'campaign_name',
                'adset_name',
                'ad_name',
                'ad_id',
                'meta_ad_account_id',
            ]);

        $totalSpend = (float) $rows->sum('spend_amount');

        $byDay = $rows
            ->groupBy(fn (MetaAdSpendDaily $row) => $row->spend_date?->toDateString() ?? '')
            ->filter(fn ($_, $date) => $date !== '')
            ->map(fn (Collection $group, string $date) => [
                'date' => $date,
                'spend' => round((float) $group->sum('spend_amount'), 2),
            ])
            ->sortKeys()
            ->values()
            ->all();

        $byAccount = self::rankSpend(
            $rows->groupBy(fn (MetaAdSpendDaily $row) => $row->account?->label
                ?: ('App '.($row->account?->app_id ?? $row->meta_ad_account_id))),
            $totalSpend,
        );

        $byAppId = self::rankSpend(
            $rows->groupBy(fn (MetaAdSpendDaily $row) => $row->account?->app_id
                ? 'App '.$row->account->app_id
                : 'Unknown app'),
            $totalSpend,
        );

        $byCampaign = self::rankSpend(
            $rows->groupBy(fn (MetaAdSpendDaily $row) => $row->campaign_name ?: 'Untitled campaign'),
            $totalSpend,
        );

        $byAdset = self::rankSpend(
            $rows->groupBy(fn (MetaAdSpendDaily $row) => $row->adset_name ?: 'Untitled ad set'),
            $totalSpend,
        );

        $byTheme = self::rankSpend(self::groupByPipeSegment($rows, 'theme'), $totalSpend);
        $byCause = self::rankSpend(self::groupByPipeSegment($rows, 'cause'), $totalSpend);

        $byMarketer = [];
        if ($includeMarketerBreakdown) {
            $byMarketer = self::rankSpend(
                $rows->groupBy(fn (MetaAdSpendDaily $row) => $row->user?->name ?: 'Unmatched'),
                $totalSpend,
            );
        }

        $byAd = self::rankSpend(
            $rows->groupBy(fn (MetaAdSpendDaily $row) => $row->ad_name ?: ('Ad '.$row->ad_id)),
            $totalSpend,
        );

        $chartLabels = collect($byDay)->map(function (array $day) {
            $date = Carbon::parse($day['date']);

            return [
                'key' => $day['date'],
                'label' => $date->format('d M'),
            ];
        })->values()->all();

        $chartSeries = [
            [
                'name' => 'Total spend',
                'points' => collect($byDay)->map(fn (array $day) => [
                    'revenue' => $day['spend'],
                ])->values()->all(),
            ],
        ];

        foreach (self::accountTrendSeries($rows, $byDay, 3) as $series) {
            $chartSeries[] = $series;
        }

        $avgDaily = count($byDay) > 0 ? round($totalSpend / count($byDay), 2) : 0.0;

        return [
            'totals' => [
                'spend' => round($totalSpend, 2),
                'ads' => $rows->pluck('ad_id')->unique()->count(),
                'campaigns' => $rows->pluck('campaign_name')->filter()->unique()->count(),
                'accounts' => $rows->pluck('meta_ad_account_id')->unique()->count(),
                'days' => count($byDay),
                'avg_daily_spend' => $avgDaily,
            ],
            'by_day' => $byDay,
            'by_account' => $byAccount,
            'by_app_id' => $byAppId,
            'by_campaign' => $byCampaign,
            'by_adset' => $byAdset,
            'by_theme' => $byTheme,
            'by_cause' => $byCause,
            'by_marketer' => $byMarketer,
            'by_ad' => $byAd,
            'chart_labels' => $chartLabels,
            'chart_series' => $chartSeries,
        ];
    }

    /**
     * @param  Collection<int, MetaAdSpendDaily>  $rows
     * @param  list<array{date: string, spend: float}>  $byDay
     * @return list<array{name: string, points: list<array{revenue: float}>}>
     */
    private static function accountTrendSeries(Collection $rows, array $byDay, int $limit = 3): array
    {
        if ($byDay === []) {
            return [];
        }

        $accountTotals = $rows
            ->groupBy('meta_ad_account_id')
            ->map(fn (Collection $group) => (float) $group->sum('spend_amount'))
            ->sortDesc()
            ->take($limit);

        $dates = array_column($byDay, 'date');
        $series = [];

        foreach ($accountTotals as $accountId => $_total) {
            $sample = $rows->firstWhere('meta_ad_account_id', (int) $accountId);
            $name = $sample?->account?->label ?: ('Account '.$accountId);

            $byDate = $rows
                ->where('meta_ad_account_id', (int) $accountId)
                ->groupBy(fn (MetaAdSpendDaily $row) => $row->spend_date?->toDateString() ?? '')
                ->map(fn (Collection $group) => (float) $group->sum('spend_amount'));

            $points = collect($dates)->map(fn (string $date) => [
                'revenue' => round((float) ($byDate[$date] ?? 0), 2),
            ])->values()->all();

            $series[] = [
                'name' => $name,
                'points' => $points,
            ];
        }

        return $series;
    }

    /**
     * @param  Collection<int, MetaAdSpendDaily>  $rows
     * @return Collection<string, Collection<int, MetaAdSpendDaily>>
     */
    private static function groupByPipeSegment(Collection $rows, string $segment): Collection
    {
        $key = match ($segment) {
            'theme' => 'theme',
            'cause' => 'cause',
            default => 'theme',
        };

        $grouped = [];

        foreach ($rows as $row) {
            $parsed = MarketerNameMatcher::parsePipeSegments($row->ad_name);
            $label = $parsed[$key] ?: 'Not set';

            if (! isset($grouped[$label])) {
                $grouped[$label] = collect();
            }

            $grouped[$label]->push($row);
        }

        return collect($grouped);
    }

    /**
     * @param  Collection<string, Collection<int, MetaAdSpendDaily>>  $groups
     * @return list<array{name: string, spend: float, percentage: float, count: float}>
     */
    private static function rankSpend(Collection $groups, float $totalSpend): array
    {
        return $groups
            ->map(function (Collection $group, string $name) use ($totalSpend) {
                $spend = round((float) $group->sum('spend_amount'), 2);

                return [
                    'name' => $name,
                    'spend' => $spend,
                    'count' => $spend,
                    'percentage' => $totalSpend > 0 ? round(($spend / $totalSpend) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('spend')
            ->take(8)
            ->values()
            ->all();
    }

    private static function lastSyncedAt(string $tz): ?string
    {
        $lastSynced = MetaAdAccount::query()->max('last_synced_at');

        return $lastSynced
            ? Carbon::parse($lastSynced)->timezone($tz)->toDateTimeString()
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeRow(MetaAdSpendDaily $row, bool $includeMarketer = true): array
    {
        $segments = MarketerNameMatcher::parsePipeSegments($row->ad_name);

        $payload = [
            'id' => $row->id,
            'spend_date' => $row->spend_date?->toDateString(),
            'account_label' => $row->account?->label,
            'app_id' => $row->account?->app_id,
            'campaign_name' => $row->campaign_name,
            'adset_name' => $row->adset_name,
            'ad_name' => $row->ad_name,
            'spend_amount' => (float) $row->spend_amount,
            'currency' => $row->currency,
            'matched_via' => $row->matched_via,
            'pipe' => [
                'marketer' => $segments['marketer'],
                'date' => $segments['date'],
                'brand' => $segments['brand'],
                'theme' => $segments['theme'],
                'cause' => $segments['cause'],
            ],
        ];

        if ($includeMarketer) {
            $payload['user_id'] = $row->user_id;
            $payload['marketer_name'] = $row->user?->name;
            $payload['marketer_code'] = $row->user?->referral_code;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeAccount(MetaAdAccount $account): array
    {
        return [
            'id' => $account->id,
            'label' => $account->label,
            'app_id' => $account->app_id,
            'ad_account_id' => $account->ad_account_id,
            'is_active' => (bool) $account->is_active,
            'has_app_secret' => $account->hasAppSecret(),
            'has_access_token' => $account->hasAccessToken(),
            'last_synced_at' => $account->last_synced_at?->timezone(config('app.timezone'))->toDateTimeString(),
            'last_sync_status' => $account->last_sync_status,
            'last_sync_error' => $account->last_sync_error,
        ];
    }
}
