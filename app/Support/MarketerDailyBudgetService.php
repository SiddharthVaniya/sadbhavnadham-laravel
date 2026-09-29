<?php

namespace App\Support;

use App\Models\MarketerDailyBudget;
use App\Models\MarketerMonthlyBudget;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarketerDailyBudgetService
{
    /**
     * @return array{
     *     spend_date: string,
     *     spend_date_label: string,
     *     year_month: string,
     *     year_month_label: string,
     *     marketers: list<array{
     *         user_id: int,
     *         name: string,
     *         email: string,
     *         code: string,
     *         month_target_amount: ?int,
     *         month_spend_amount: float,
     *         limit_amount: ?float,
     *         spend_amount: float
     *     }>
     * }
     */
    public static function todayIndex(?Carbon $reference = null): array
    {
        $reference ??= now();
        $spendDate = MarketerDailyBudget::todayDate($reference);
        $month = MarketerMonthlyBudgetService::adminIndex($reference);

        $dailyRows = MarketerDailyBudget::query()
            ->whereDate('spend_date', $spendDate)
            ->get(['user_id', 'limit_amount', 'spend_amount'])
            ->keyBy('user_id');

        return [
            'spend_date' => $spendDate,
            'spend_date_label' => $reference->format('d M Y'),
            'year_month' => $month['year_month'],
            'year_month_label' => $month['year_month_label'],
            'marketers' => collect($month['marketers'])->map(function (array $marketer) use ($dailyRows): array {
                $daily = $dailyRows->get($marketer['user_id']);

                return [
                    'user_id' => $marketer['user_id'],
                    'name' => $marketer['name'],
                    'email' => $marketer['email'],
                    'code' => $marketer['code'],
                    'month_target_amount' => $marketer['target_amount'],
                    'month_spend_amount' => (float) $marketer['spend_amount'],
                    'limit_amount' => $daily?->limit_amount !== null ? (float) $daily->limit_amount : null,
                    'spend_amount' => (float) ($daily?->spend_amount ?? 0),
                ];
            })->values()->all(),
        ];
    }

    public static function upsertForDate(
        User $user,
        ?float $limitAmount,
        float|int|string|null $spendAmount,
        ?Carbon $reference = null,
    ): MarketerDailyBudget {
        $spend = $spendAmount === null || $spendAmount === ''
            ? 0.0
            : (float) $spendAmount;

        return MarketerDailyBudget::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'spend_date' => MarketerDailyBudget::todayDate($reference),
            ],
            [
                'limit_amount' => $limitAmount,
                'spend_amount' => $spend,
            ],
        );
    }

    /**
     * @return array{
     *     filters: array<string, string>,
     *     marketers: list<array{id: int, name: string, code: string}>,
     *     rows: array{data: list<array<string, mixed>>, links: mixed, meta: array<string, mixed>}
     * }
     */
    public static function history(Request $request): array
    {
        $filters = [
            'user_id' => trim((string) $request->input('user_id', '')),
            'from_date' => trim((string) $request->input('from_date', '')),
            'to_date' => trim((string) $request->input('to_date', '')),
            'year_month' => trim((string) $request->input('year_month', '')),
            'archive' => trim((string) $request->input('archive', 'all')),
            'q' => trim((string) $request->input('q', '')),
        ];

        if (! in_array($filters['archive'], ['all', 'today', 'archived'], true)) {
            $filters['archive'] = 'all';
        }

        $today = now()->toDateString();

        $query = MarketerDailyBudget::query()
            ->with('user:id,name,email,referral_code')
            ->orderByDesc('spend_date')
            ->orderBy('user_id');

        if ($filters['user_id'] !== '' && ctype_digit($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if ($filters['from_date'] !== '') {
            $query->whereDate('spend_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] !== '') {
            $query->whereDate('spend_date', '<=', $filters['to_date']);
        }

        if (preg_match('/^\d{4}-\d{2}$/', $filters['year_month']) === 1) {
            $start = Carbon::createFromFormat('Y-m', $filters['year_month'])->startOfMonth()->toDateString();
            $end = Carbon::createFromFormat('Y-m', $filters['year_month'])->endOfMonth()->toDateString();
            $query->whereDate('spend_date', '>=', $start)->whereDate('spend_date', '<=', $end);
        }

        if ($filters['archive'] === 'today') {
            $query->whereDate('spend_date', $today);
        } elseif ($filters['archive'] === 'archived') {
            $query->whereDate('spend_date', '<', $today);
        }

        if ($filters['q'] !== '') {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->whereHas('user', function (Builder $userQuery) use ($term): void {
                $userQuery->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('referral_code', 'like', $term);
            });
        }

        /** @var LengthAwarePaginator<int, MarketerDailyBudget> $paginator */
        $paginator = $query->paginate(AdminInertiaResources::LIST_PER_PAGE)->withQueryString();

        $monthKeys = collect($paginator->items())
            ->map(fn (MarketerDailyBudget $row) => $row->spend_date?->format('Y-m'))
            ->filter()
            ->unique()
            ->values();

        $monthly = MarketerMonthlyBudget::query()
            ->whereIn('user_id', collect($paginator->items())->pluck('user_id')->unique()->all())
            ->whereIn('year_month', $monthKeys->all())
            ->get()
            ->keyBy(fn (MarketerMonthlyBudget $budget) => $budget->user_id.'|'.$budget->year_month);

        $dailyMonthSpend = [];
        if ($monthKeys->isNotEmpty()) {
            $rangeStart = Carbon::createFromFormat('Y-m', (string) $monthKeys->min())->startOfMonth()->toDateString();
            $rangeEnd = Carbon::createFromFormat('Y-m', (string) $monthKeys->max())->endOfMonth()->toDateString();
            $dailyRows = MarketerDailyBudget::query()
                ->whereIn('user_id', collect($paginator->items())->pluck('user_id')->unique()->all())
                ->whereDate('spend_date', '>=', $rangeStart)
                ->whereDate('spend_date', '<=', $rangeEnd)
                ->get(['user_id', 'spend_date', 'spend_amount']);

            foreach ($dailyRows as $dailyRow) {
                $key = $dailyRow->user_id.'|'.$dailyRow->spend_date?->format('Y-m');
                $dailyMonthSpend[$key] = ($dailyMonthSpend[$key] ?? 0) + (float) $dailyRow->spend_amount;
            }
        }

        $marketers = User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'referral_code'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'code' => (string) $user->referral_code,
            ])
            ->values()
            ->all();

        return [
            'filters' => $filters,
            'marketers' => $marketers,
            'rows' => AdminInertiaResources::paginated(
                $paginator,
                function (MarketerDailyBudget $row) use ($monthly, $dailyMonthSpend, $today): array {
                    $yearMonth = $row->spend_date?->format('Y-m') ?? '';
                    $budget = $monthly->get($row->user_id.'|'.$yearMonth);
                    $date = $row->spend_date?->toDateString() ?? '';

                    return [
                        'id' => $row->id,
                        'user_id' => $row->user_id,
                        'name' => $row->user?->name ?? '—',
                        'email' => $row->user?->email ?? '',
                        'code' => (string) ($row->user?->referral_code ?? ''),
                        'spend_date' => $date,
                        'spend_date_label' => $row->spend_date?->format('d M Y') ?? '',
                        'year_month' => $yearMonth,
                        'month_target_amount' => $budget?->target_amount,
                        'month_spend_amount' => (float) ($dailyMonthSpend[$row->user_id.'|'.$yearMonth] ?? 0),
                        'limit_amount' => $row->limit_amount !== null ? (float) $row->limit_amount : null,
                        'spend_amount' => (float) $row->spend_amount,
                        'archive' => $date !== '' && $date < $today ? 'archived' : 'today',
                    ];
                },
            ),
        ];
    }
}
