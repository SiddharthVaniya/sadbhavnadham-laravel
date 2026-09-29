<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMarketerDailyBudgetsRequest;
use App\Http\Requests\Admin\UpdateMarketerMonthlyBudgetsRequest;
use App\Models\MarketerDailyBudget;
use App\Models\User;
use App\Support\MarketerDailyBudgetService;
use App\Support\MarketerMonthlyBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminMarketerBudgetController extends Controller
{
    public function index(): Response
    {
        $payload = MarketerMonthlyBudgetService::adminIndex();

        return Inertia::render('Admin/Marketers/Index', [
            'yearMonth' => $payload['year_month'],
            'yearMonthLabel' => $payload['year_month_label'],
            'marketers' => $payload['marketers'],
        ]);
    }

    public function update(UpdateMarketerMonthlyBudgetsRequest $request): RedirectResponse
    {
        $rows = $request->validated('marketers');

        foreach ($rows as $row) {
            $user = User::query()->find($row['user_id']);

            if (! $user) {
                continue;
            }

            $target = array_key_exists('target_amount', $row) && $row['target_amount'] !== null && $row['target_amount'] !== ''
                ? (int) $row['target_amount']
                : null;

            MarketerMonthlyBudgetService::upsertForCurrentMonth(
                $user,
                $target,
                MarketerMonthlyBudgetService::dailySpendTotals([$user->id])[$user->id] ?? 0,
            );
        }

        return redirect()
            ->route('admin.marketers.index')
            ->with('status', 'This month marketer targets saved.');
    }

    public function today(Request $request): Response
    {
        $reference = $this->selectedSpendDate($request->query('date'));
        $payload = MarketerDailyBudgetService::todayIndex($reference);

        return Inertia::render('Admin/Marketers/Today', [
            'spendDate' => $payload['spend_date'],
            'spendDateLabel' => $payload['spend_date_label'],
            'maxDate' => now()->toDateString(),
            'yearMonth' => $payload['year_month'],
            'yearMonthLabel' => $payload['year_month_label'],
            'marketers' => $payload['marketers'],
        ]);
    }

    public function updateToday(UpdateMarketerDailyBudgetsRequest $request): RedirectResponse
    {
        $spendDate = $request->validated('spend_date');
        $reference = is_string($spendDate) && $spendDate !== ''
            ? Carbon::parse($spendDate)->startOfDay()
            : now();

        foreach ($request->validated('marketers') as $row) {
            $user = User::query()->find($row['user_id']);

            if (! $user) {
                continue;
            }

            $existingDaily = MarketerDailyBudget::query()
                ->where('user_id', $user->id)
                ->whereDate('spend_date', $reference->toDateString())
                ->first();

            MarketerDailyBudgetService::upsertForDate(
                $user,
                $existingDaily?->limit_amount !== null ? (float) $existingDaily->limit_amount : null,
                $row['spend_amount'] ?? 0,
                $reference,
            );

            MarketerMonthlyBudgetService::syncSpendFromDaily($user, $reference);

            if (array_key_exists('month_limit_amount', $row)) {
                $monthLimit = $row['month_limit_amount'] !== null && $row['month_limit_amount'] !== ''
                    ? (int) $row['month_limit_amount']
                    : null;
                $synced = MarketerMonthlyBudgetService::forUserMonth($user, $reference);

                MarketerMonthlyBudgetService::upsertForCurrentMonth(
                    $user,
                    $monthLimit,
                    $synced['spend_amount'],
                    $reference,
                );
            }
        }

        return redirect()
            ->route('admin.marketers.today', ['date' => $reference->toDateString()])
            ->with('status', 'Spending for '.$reference->format('d M Y').' saved.');
    }

    private function selectedSpendDate(mixed $value): Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return now();
        }

        $date = Carbon::createFromFormat('Y-m-d', $value);

        if (! $date instanceof Carbon || $date->format('Y-m-d') !== $value) {
            return now();
        }

        $date = $date->startOfDay();

        if ($date->isFuture()) {
            return now();
        }

        return $date;
    }

    public function history(Request $request): Response
    {
        $payload = MarketerDailyBudgetService::history($request);

        return Inertia::render('Admin/Marketers/History', $payload);
    }
}
