<?php

namespace App\Support;

use App\Models\MetaAdAccount;
use App\Models\MetaAdSpendDaily;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MetaAdSpendQuery
{
    /**
     * @return array{
     *     filters: array<string, string>,
     *     accounts: list<array{id: int, label: string}>,
     *     marketers: list<array{id: int, name: string, code: string}>,
     *     rows: LengthAwarePaginator,
     *     unmatched_count: int,
     *     last_synced_at: ?string
     * }
     */
    public static function adminIndex(Request $request): array
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $today = now($tz)->toDateString();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'user_id' => trim((string) $request->input('user_id', '')),
            'meta_ad_account_id' => trim((string) $request->input('meta_ad_account_id', '')),
            'from_date' => trim((string) $request->input('from_date', $today)),
            'to_date' => trim((string) $request->input('to_date', $today)),
            'campaign' => trim((string) $request->input('campaign', '')),
            'adset' => trim((string) $request->input('adset', '')),
            'match' => trim((string) $request->input('match', 'all')),
            'cause' => trim((string) $request->input('cause', '')),
        ];

        if (! in_array($filters['match'], ['all', 'matched', 'unmatched'], true)) {
            $filters['match'] = 'all';
        }

        if ($filters['from_date'] === '') {
            $filters['from_date'] = $today;
        }

        if ($filters['to_date'] === '') {
            $filters['to_date'] = $today;
        }

        $query = self::baseQuery($filters);

        $unmatchedCount = (clone $query)
            ->where('matched_via', MetaAdSpendDaily::MATCHED_VIA_UNMATCHED)
            ->count();

        $rows = $query
            ->with(['user:id,name,referral_code', 'account:id,label'])
            ->orderByDesc('spend_date')
            ->orderByDesc('spend_amount')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString()
            ->through(fn (MetaAdSpendDaily $row) => self::serializeRow($row));

        $lastSynced = MetaAdAccount::query()->max('last_synced_at');

        return [
            'filters' => $filters,
            'accounts' => MetaAdAccount::query()
                ->orderBy('label')
                ->get(['id', 'label'])
                ->map(fn (MetaAdAccount $a) => ['id' => $a->id, 'label' => $a->label])
                ->values()
                ->all(),
            'marketers' => MarketerNameMatcher::marketerOptions()->values()->all(),
            'rows' => $rows,
            'unmatched_count' => $unmatchedCount,
            'last_synced_at' => $lastSynced
                ? Carbon::parse($lastSynced)->timezone($tz)->toDateTimeString()
                : null,
        ];
    }

    /**
     * @return array{
     *     filters: array<string, string>,
     *     rows: LengthAwarePaginator,
     *     last_synced_at: ?string
     * }
     */
    public static function marketerIndex(Request $request, int $userId): array
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $today = now($tz)->toDateString();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'from_date' => trim((string) $request->input('from_date', $today)),
            'to_date' => trim((string) $request->input('to_date', $today)),
        ];

        if ($filters['from_date'] === '') {
            $filters['from_date'] = $today;
        }

        if ($filters['to_date'] === '') {
            $filters['to_date'] = $today;
        }

        $query = MetaAdSpendDaily::query()
            ->where('user_id', $userId)
            ->whereDate('spend_date', '>=', $filters['from_date'])
            ->whereDate('spend_date', '<=', $filters['to_date']);

        MarketerNameMatcher::applySmartSearch($query, $filters['q']);

        $rows = $query
            ->with(['account:id,label'])
            ->orderByDesc('spend_date')
            ->orderByDesc('spend_amount')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString()
            ->through(fn (MetaAdSpendDaily $row) => self::serializeRow($row, false));

        $lastSynced = MetaAdAccount::query()
            ->where('is_active', true)
            ->max('last_synced_at');

        return [
            'filters' => $filters,
            'rows' => $rows,
            'last_synced_at' => $lastSynced
                ? Carbon::parse($lastSynced)->timezone($tz)->toDateTimeString()
                : null,
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<MetaAdSpendDaily>
     */
    private static function baseQuery(array $filters): Builder
    {
        $query = MetaAdSpendDaily::query()
            ->whereDate('spend_date', '>=', $filters['from_date'])
            ->whereDate('spend_date', '<=', $filters['to_date']);

        MarketerNameMatcher::applySmartSearch($query, $filters['q']);
        MarketerNameMatcher::applyMarketerFilter($query, $filters['user_id']);

        if ($filters['meta_ad_account_id'] !== '') {
            $query->where('meta_ad_account_id', (int) $filters['meta_ad_account_id']);
        }

        if ($filters['campaign'] !== '') {
            $like = '%'.addcslashes($filters['campaign'], '%_\\').'%';
            $query->where('campaign_name', 'like', $like);
        }

        if ($filters['adset'] !== '') {
            $like = '%'.addcslashes($filters['adset'], '%_\\').'%';
            $query->where('adset_name', 'like', $like);
        }

        if ($filters['cause'] !== '') {
            $like = '%'.addcslashes($filters['cause'], '%_\\').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('ad_name', 'like', $like)
                    ->orWhere('campaign_name', 'like', $like);
            });
        }

        if ($filters['match'] === 'matched') {
            $query->where('matched_via', MetaAdSpendDaily::MATCHED_VIA_AD_NAME_PREFIX);
        } elseif ($filters['match'] === 'unmatched') {
            $query->where('matched_via', MetaAdSpendDaily::MATCHED_VIA_UNMATCHED);
        }

        return $query;
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
