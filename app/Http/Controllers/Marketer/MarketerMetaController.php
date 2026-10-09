<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Jobs\SyncMetaAdSpendJob;
use App\Support\MetaAdSpendQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class MarketerMetaController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $payload = MetaAdSpendQuery::marketerIndex($request, $user->id);

        return Inertia::render('Marketer/Meta', [
            'filters' => $payload['filters'],
            'filterOptions' => $payload['filter_options'],
            'analytics' => $payload['analytics'],
            'rows' => $payload['rows'],
            'lastSyncedAt' => $payload['last_synced_at'],
        ]);
    }

    public function refresh(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $key = 'meta-sync:marketer:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->with('error', "Please wait {$seconds}s before refreshing Meta again.");
        }

        RateLimiter::hit($key, 300);

        $tz = config('app.timezone', 'Asia/Kolkata');
        $from = now($tz)->subDay()->toDateString();
        $to = now($tz)->toDateString();

        $job = new SyncMetaAdSpendJob(null, $from, $to);
        $result = $job->handle(app(\App\Services\Meta\MetaAdSpendSyncService::class));

        $message = sprintf(
            'Meta refresh finished: %d account(s) ok, %d insight rows.',
            $result['accounts_synced'],
            $result['rows_upserted'],
        );

        if ($result['accounts_failed'] > 0 && $result['accounts_synced'] === 0) {
            return back()->with('error', $message.' '.(implode(' ', array_slice($result['errors'], 0, 1))));
        }

        return back()->with('status', $message);
    }
}
