<?php

namespace App\Support;

use App\Models\MarketerMonthlyBudget;
use App\Models\User;
use Illuminate\Support\Carbon;

class MarketerMonthlyBudgetService
{
    /**
     * @return array{year_month: string, target_amount: ?int, spend_amount: float}
     */
    public static function forUserMonth(User $user, ?Carbon $reference = null): array
    {
        $yearMonth = MarketerMonthlyBudget::currentYearMonth($reference);
        $budget = MarketerMonthlyBudget::query()
            ->where('user_id', $user->id)
            ->where('year_month', $yearMonth)
            ->first();

        return [
            'year_month' => $yearMonth,
            'target_amount' => $budget?->target_amount,
            'spend_amount' => (float) ($budget?->spend_amount ?? 0),
        ];
    }

    public static function upsertForCurrentMonth(
        User $user,
        ?int $targetAmount,
        float|int|string|null $spendAmount,
        ?Carbon $reference = null,
    ): MarketerMonthlyBudget {
        $yearMonth = MarketerMonthlyBudget::currentYearMonth($reference);
        $spend = $spendAmount === null || $spendAmount === ''
            ? 0.0
            : (float) $spendAmount;

        return MarketerMonthlyBudget::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'year_month' => $yearMonth,
            ],
            [
                'target_amount' => $targetAmount,
                'spend_amount' => $spend,
            ],
        );
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{target_amount: ?int, spend_amount: float}>
     */
    public static function mapForUsers(array $userIds, ?Carbon $reference = null): array
    {
        if ($userIds === []) {
            return [];
        }

        $yearMonth = MarketerMonthlyBudget::currentYearMonth($reference);
        $rows = MarketerMonthlyBudget::query()
            ->where('year_month', $yearMonth)
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'target_amount', 'spend_amount']);

        $map = [];

        foreach ($userIds as $userId) {
            $map[(int) $userId] = [
                'target_amount' => null,
                'spend_amount' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $map[(int) $row->user_id] = [
                'target_amount' => $row->target_amount,
                'spend_amount' => (float) $row->spend_amount,
            ];
        }

        return $map;
    }
}
