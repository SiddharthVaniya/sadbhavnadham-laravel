<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMarketerMonthlyBudgetsRequest;
use App\Models\User;
use App\Support\MarketerMonthlyBudgetService;
use Illuminate\Http\RedirectResponse;
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
                $row['spend_amount'] ?? 0,
            );
        }

        return redirect()
            ->route('admin.marketers.index')
            ->with('status', 'This month marketer targets and spend saved.');
    }
}
