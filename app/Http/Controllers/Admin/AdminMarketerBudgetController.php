<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMarketerDailyBudgetsRequest;
use App\Http\Requests\Admin\UpdateMarketerMonthlyBudgetsRequest;
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
        $editingYesterday = $request->query('day') === 'yesterday';
        $reference = $editingYesterday ? now()->subDay() : now();
        $payload = MarketerDailyBudgetService::todayIndex($reference);

        return Inertia::render('Admin/Marketers/Today', [
            'editingYesterday' => $editingYesterday,
            'spendDate' => $payload['spend_date'],
            'spendDateLabel' => $payload['spend_date_label'],
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

            $limit = array_key_exists('limit_amount', $row) && $row['limit_amount'] !== null && $row['limit_amount'] !== ''
                ? (float) $row['limit_amount']
                : null;

            MarketerDailyBudgetService::upsertForDate(
                $user,
                $limit,
                $row['spend_amount'] ?? 0,
                $reference,
            );

            MarketerMonthlyBudgetService::syncSpendFromDaily($user, $reference);
        }

        $editingYesterday = $reference->toDateString() === now()->subDay()->toDateString();

        return redirect()
            ->route('admin.marketers.today', $editingYesterday ? ['day' => 'yesterday'] : [])
            ->with('status', $editingYesterday ? 'Yesterday’s spending limits saved.' : 'Today’s spending limits saved.');
    }

    public function history(Request $request): Response
    {
        $payload = MarketerDailyBudgetService::history($request);

        return Inertia::render('Admin/Marketers/History', $payload);
    }
}
