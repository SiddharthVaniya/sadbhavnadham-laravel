<?php

namespace App\Services\Meta;

use App\Models\MarketerDailyBudget;
use App\Models\MetaAdAccount;
use App\Models\MetaAdSpendDaily;
use App\Models\User;
use App\Support\MarketerDailyBudgetService;
use App\Support\MarketerMonthlyBudgetService;
use App\Support\MarketerNameMatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaAdSpendSyncService
{
    public function __construct(
        private readonly MetaMarketingApiClient $client,
    ) {}

    /**
     * @return array{
     *     accounts_synced: int,
     *     accounts_failed: int,
     *     rows_upserted: int,
     *     marketers_updated: int,
     *     errors: list<string>
     * }
     */
    public function sync(
        ?int $metaAdAccountId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $from ??= now($tz)->subDay()->startOfDay();
        $to ??= now($tz)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $since = $from->toDateString();
        $until = $to->toDateString();

        $query = MetaAdAccount::query()->where('is_active', true);

        if ($metaAdAccountId !== null) {
            $query->where('id', $metaAdAccountId);
        }

        $accounts = $query->get();

        $result = [
            'accounts_synced' => 0,
            'accounts_failed' => 0,
            'rows_upserted' => 0,
            'marketers_updated' => 0,
            'errors' => [],
        ];

        foreach ($accounts as $account) {
            try {
                $insights = $this->client->fetchAdInsights($account, $since, $until);
                $result['rows_upserted'] += $this->upsertInsights($account, $insights);
                $result['accounts_synced']++;

                $account->forceFill([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'success',
                    'last_sync_error' => null,
                ])->save();
            } catch (Throwable $e) {
                $result['accounts_failed']++;
                $result['errors'][] = $account->label.': '.$e->getMessage();

                $account->forceFill([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'error',
                    'last_sync_error' => mb_substr($e->getMessage(), 0, 1000),
                ])->save();

                Log::warning('meta.spend_sync.account_failed', [
                    'account_id' => $account->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $result['marketers_updated'] = $this->overwriteDailySpendFromSnapshots($since, $until, $tz);

        return $result;
    }

    /**
     * Sync Kolkata today + yesterday (late Insights).
     *
     * @return array<string, mixed>
     */
    public function syncRecentDays(?int $metaAdAccountId = null): array
    {
        $tz = config('app.timezone', 'Asia/Kolkata');

        return $this->sync(
            $metaAdAccountId,
            now($tz)->subDay()->startOfDay(),
            now($tz)->startOfDay(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     */
    private function upsertInsights(MetaAdAccount $account, array $insights): int
    {
        $count = 0;

        DB::transaction(function () use ($account, $insights, &$count): void {
            foreach ($insights as $row) {
                $attribution = MarketerNameMatcher::resolveAttribution($row['ad_name'] ?? null);

                MetaAdSpendDaily::query()->updateOrCreate(
                    [
                        'meta_ad_account_id' => $account->id,
                        'spend_date' => $row['spend_date'],
                        'ad_id' => $row['ad_id'],
                    ],
                    [
                        'campaign_id' => $row['campaign_id'],
                        'campaign_name' => $row['campaign_name'],
                        'adset_id' => $row['adset_id'],
                        'adset_name' => $row['adset_name'],
                        'ad_name' => $row['ad_name'],
                        'spend_amount' => $row['spend_amount'],
                        'currency' => $row['currency'] ?? 'INR',
                        'user_id' => $attribution['user_id'],
                        'matched_via' => $attribution['matched_via'],
                    ],
                );

                $count++;
            }
        });

        return $count;
    }

    /**
     * Overwrite marketer_daily_budgets.spend_amount from SUM of matched Meta rows.
     * Days with no matched Meta rows are left unchanged.
     */
    private function overwriteDailySpendFromSnapshots(string $since, string $until, string $tz): int
    {
        $aggregates = MetaAdSpendDaily::query()
            ->whereDate('spend_date', '>=', $since)
            ->whereDate('spend_date', '<=', $until)
            ->whereNotNull('user_id')
            ->groupBy('user_id', 'spend_date')
            ->selectRaw('user_id, spend_date, SUM(spend_amount) as total')
            ->get();

        /** @var array<string, true> $touchedUserMonths */
        $touchedUserMonths = [];
        $updated = 0;

        foreach ($aggregates as $row) {
            $user = User::query()->find((int) $row->user_id);

            if (! $user) {
                continue;
            }

            $date = Carbon::parse($row->spend_date, $tz)->toDateString();
            $reference = Carbon::parse($date, $tz)->startOfDay();
            $existing = MarketerDailyBudget::query()
                ->where('user_id', $user->id)
                ->whereDate('spend_date', $date)
                ->first();

            MarketerDailyBudgetService::upsertForDate(
                $user,
                $existing?->limit_amount,
                (float) $row->total,
                $reference,
            );

            $touchedUserMonths[$user->id.':'.$reference->format('Y-m')] = true;
            $updated++;
        }

        foreach (array_keys($touchedUserMonths) as $key) {
            [$userId, $yearMonth] = explode(':', $key, 2);
            $user = User::query()->find((int) $userId);

            if (! $user) {
                continue;
            }

            $reference = Carbon::createFromFormat('Y-m', $yearMonth, $tz)->startOfMonth();
            MarketerMonthlyBudgetService::syncSpendFromDaily($user, $reference);
        }

        return $updated;
    }
}
