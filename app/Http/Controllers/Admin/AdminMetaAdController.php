<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncMetaAdSpendJob;
use App\Models\MetaAdAccount;
use App\Support\MetaAdSpendQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class AdminMetaAdController extends Controller
{
    public function index(Request $request): Response
    {
        $payload = MetaAdSpendQuery::adminIndex($request);

        return Inertia::render('Admin/Meta/Index', [
            'filters' => $payload['filters'],
            'filterOptions' => $payload['filter_options'],
            'analytics' => $payload['analytics'],
            'rows' => $payload['rows'],
            'unmatchedCount' => $payload['unmatched_count'],
            'lastSyncedAt' => $payload['last_synced_at'],
        ]);
    }

    public function accounts(): Response
    {
        $accounts = MetaAdAccount::query()
            ->orderBy('label')
            ->get()
            ->map(fn (MetaAdAccount $account) => MetaAdSpendQuery::serializeAccount($account))
            ->values()
            ->all();

        $lastSynced = MetaAdAccount::query()->max('last_synced_at');

        return Inertia::render('Admin/Meta/Accounts', [
            'accounts' => $accounts,
            'analytics' => MetaAdSpendQuery::accountsOverviewAnalytics(),
            'lastSyncedAt' => $lastSynced
                ? Carbon::parse($lastSynced)->timezone(config('app.timezone'))->toDateTimeString()
                : null,
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'app_id' => ['required', 'string', 'max:255'],
            'app_secret' => ['required', 'string', 'max:65535'],
            'access_token' => ['required', 'string', 'max:65535'],
            'ad_account_id' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        MetaAdAccount::query()->create([
            'label' => trim($data['label']),
            'app_id' => trim($data['app_id']),
            // Store secrets/tokens exactly as pasted (trim outer whitespace only).
            'app_secret' => trim($data['app_secret']),
            'access_token' => trim($data['access_token']),
            'ad_account_id' => trim($data['ad_account_id']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.meta.accounts')
            ->with('status', 'Meta ad account saved.');
    }

    public function updateAccount(Request $request, MetaAdAccount $metaAdAccount): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'app_id' => ['required', 'string', 'max:255'],
            'app_secret' => ['nullable', 'string', 'max:65535'],
            'access_token' => ['nullable', 'string', 'max:65535'],
            'ad_account_id' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $payload = [
            'label' => trim($data['label']),
            'app_id' => trim($data['app_id']),
            'ad_account_id' => trim($data['ad_account_id']),
            'is_active' => $request->boolean('is_active', $metaAdAccount->is_active),
        ];

        $appSecret = trim((string) ($data['app_secret'] ?? ''));
        $accessToken = trim((string) ($data['access_token'] ?? ''));

        // Blank / masked placeholder keeps the existing encrypted value unchanged.
        if ($appSecret !== '' && $appSecret !== '********') {
            $payload['app_secret'] = $appSecret;
        }

        if ($accessToken !== '' && $accessToken !== '********') {
            $payload['access_token'] = $accessToken;
        }

        if (! isset($payload['app_secret']) && ! $metaAdAccount->hasAppSecret()) {
            return back()->withErrors(['app_secret' => 'App secret is required.'])->withInput();
        }

        if (! isset($payload['access_token']) && ! $metaAdAccount->hasAccessToken()) {
            return back()->withErrors(['access_token' => 'Access token is required.'])->withInput();
        }

        if (isset($payload['app_secret']) || isset($payload['access_token'])) {
            $payload['last_sync_status'] = null;
            $payload['last_sync_error'] = null;
        }

        $metaAdAccount->update($payload);

        return redirect()
            ->route('admin.meta.accounts')
            ->with('status', 'Meta ad account updated.');
    }

    public function destroyAccount(MetaAdAccount $metaAdAccount): RedirectResponse
    {
        $metaAdAccount->delete();

        return redirect()
            ->route('admin.meta.accounts')
            ->with('status', 'Meta ad account removed.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'meta_ad_account_id' => ['nullable', 'integer', 'exists:meta_ad_accounts,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'redirect' => ['nullable', 'string', 'max:64'],
        ]);

        $key = 'meta-sync:admin:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->with('error', "Please wait {$seconds}s before syncing Meta again.");
        }

        RateLimiter::hit($key, 60);

        $tz = config('app.timezone', 'Asia/Kolkata');
        $from = isset($data['from']) ? Carbon::parse($data['from'], $tz)->toDateString() : null;
        $to = isset($data['to']) ? Carbon::parse($data['to'], $tz)->toDateString() : null;

        if ($from === null && $to === null) {
            $from = now($tz)->subDay()->toDateString();
            $to = now($tz)->toDateString();
        } elseif ($from !== null && $to === null) {
            $to = $from;
        }

        $job = new SyncMetaAdSpendJob(
            isset($data['meta_ad_account_id']) ? (int) $data['meta_ad_account_id'] : null,
            $from,
            $to,
        );

        $result = $job->handle(app(\App\Services\Meta\MetaAdSpendSyncService::class));

        $message = sprintf(
            'Meta sync finished: %d account(s) ok, %d failed, %d insight rows, %d marketer day updates.',
            $result['accounts_synced'],
            $result['accounts_failed'],
            $result['rows_upserted'],
            $result['marketers_updated'],
        );

        if ($result['errors'] !== []) {
            $message .= ' '.implode(' ', array_slice($result['errors'], 0, 2));
        }

        $redirect = $this->redirectAfterSync($data['redirect'] ?? null, $request);

        return $redirect->with(
            $result['accounts_failed'] > 0 && $result['accounts_synced'] === 0 ? 'error' : 'status',
            $message,
        );
    }

    private function redirectAfterSync(?string $redirect, Request $request): RedirectResponse
    {
        return match ($redirect) {
            'today' => redirect()->route('admin.marketers.today', array_filter([
                'date' => $request->input('date'),
            ])),
            'month' => redirect()->route('admin.marketers.index'),
            'history' => redirect()->route('admin.marketers.history', $request->only([
                'q', 'user_id', 'from_date', 'to_date', 'year_month', 'archive',
            ])),
            'accounts' => redirect()->route('admin.meta.accounts'),
            default => redirect()->route('admin.meta.index', $request->only([
                'q', 'user_id', 'meta_ad_account_id', 'from_date', 'to_date', 'campaign', 'adset', 'match', 'cause',
            ])),
        };
    }
}
