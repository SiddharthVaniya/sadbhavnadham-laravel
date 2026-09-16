<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelSubscriptionRequest;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Services\DonationAttributionService;
use App\Services\RazorpaySubscriptionService;
use App\Support\AdminInertiaResources;
use App\Support\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminSubscriptionController extends Controller
{
    public function __construct(private RazorpaySubscriptionService $subscriptionService) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->input('status', 'live');
        $query = $this->filteredQuery($request);

        $overviewBase = clone $query;

        $statusCounts = DonationSubscription::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        [$sort, $dir] = $this->resolveSubscriptionsListSort($request);

        $subscriptions = $query
            ->with(['cause:id,title', 'package:id,title']);

        $this->applySubscriptionsListSort($subscriptions, $sort, $dir);

        $subscriptions = $subscriptions
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        $campaignOptions = DonationSubscription::query()
            ->whereNotNull('utm_campaign')
            ->where('utm_campaign', '!=', '')
            ->distinct()
            ->orderBy('utm_campaign')
            ->limit(150)
            ->pluck('utm_campaign')
            ->values();

        $employeeOptions = DonationSubscription::query()
            ->whereNotNull('utm_content')
            ->where('utm_content', '!=', '')
            ->distinct()
            ->orderBy('utm_content')
            ->limit(150)
            ->pluck('utm_content')
            ->values();

        $activeFilterCount = collect([
            $request->filled('cause_id'),
            $request->filled('package_id'),
            $request->filled('cause_title'),
            $request->filled('search'),
            $request->filled('source'),
            $request->filled('platform'),
            $request->filled('utm_campaign'),
            $request->filled('utm_content'),
        ])->filter()->count();

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => AdminInertiaResources::paginated(
                $subscriptions,
                fn (DonationSubscription $subscription) => AdminInertiaResources::subscriptionListRow($subscription)
            ),
            'stats' => [
                'live' => DonationSubscription::query()->live()->count(),
                'active' => (int) ($statusCounts[DonationSubscription::STATUS_ACTIVE] ?? 0),
                'halted' => (int) ($statusCounts[DonationSubscription::STATUS_HALTED] ?? 0),
                'cancelled' => (int) ($statusCounts[DonationSubscription::STATUS_CANCELLED] ?? 0),
                'monthly_value' => (float) DonationSubscription::query()->live()->sum('total_amount'),
                'filtered_count' => (clone $overviewBase)->count(),
            ],
            'statusTabs' => $this->statusTabs($statusCounts),
            'statusOptions' => SubscriptionStatus::labels(),
            'causes' => Cause::query()->orderBy('title')->get(['id', 'title']),
            'packages' => CausePackage::query()
                ->with('cause:id,title')
                ->orderBy('cause_id')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(['id', 'cause_id', 'title'])
                ->map(fn (CausePackage $package) => [
                    'id' => $package->id,
                    'cause_id' => $package->cause_id,
                    'title' => $package->title,
                    'label' => $package->cause
                        ? $package->cause->title.' · '.$package->title
                        : $package->title,
                ])
                ->values(),
            'sourceOptions' => collect(DonationAttributionService::trafficSourceOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'platformOptions' => collect(DonationAttributionService::platformOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'campaignOptions' => $campaignOptions,
            'employeeOptions' => $employeeOptions,
            'activeFilterCount' => $activeFilterCount,
            'filters' => [
                'status' => $status,
                'cause_id' => $request->input('cause_id', ''),
                'package_id' => $request->input('package_id', ''),
                'cause_title' => $request->input('cause_title', ''),
                'search' => $request->input('search', ''),
                'source' => $request->input('source', ''),
                'platform' => $request->input('platform', ''),
                'utm_campaign' => $request->input('utm_campaign', ''),
                'utm_content' => $request->input('utm_content', ''),
            ],
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$sort, $dir] = $this->resolveSubscriptionsListSort($request);

        $exportQuery = $this->filteredQuery($request)
            ->with(['cause:id,title', 'package:id,title'])
            ->withSum([
                'donationOrders as collected_amount' => fn ($query) => $query->where('status', DonationOrder::STATUS_PAID),
            ], 'total_amount');

        $this->applySubscriptionsListSort($exportQuery, $sort, $dir);

        $subscriptions = $exportQuery->get();

        $filename = 'subscriptions_'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($subscriptions): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Subscription UUID',
                'Razorpay Subscription ID',
                'Donor Name',
                'Donor Email',
                'Donor Phone',
                'Cause',
                'Package',
                'Frequency',
                'Amount (INR)',
                'Status',
                'Source',
                'UTM Campaign',
                'UTM Content',
                'Billing Cycles',
                'Collected (INR)',
                'Started At',
                'Next Charge At',
                'Cancelled At',
                'Cancel Reason',
                'Created At',
            ]);

            foreach ($subscriptions as $subscription) {
                fputcsv($file, [
                    $subscription->subscription_uuid,
                    $subscription->razorpay_subscription_id,
                    $subscription->donor_name,
                    $subscription->donor_email,
                    $subscription->donor_phone,
                    $subscription->cause?->title,
                    $subscription->package?->title ?? $subscription->item_title,
                    $subscription->frequencyLabel(),
                    $subscription->total_amount,
                    $subscription->statusLabel(),
                    DonationAttributionService::trafficSourceLabel($subscription),
                    $subscription->utm_campaign,
                    $subscription->utm_content,
                    $subscription->billing_cycle_count,
                    (float) ($subscription->collected_amount ?? 0),
                    $subscription->started_at?->format('Y-m-d H:i:s'),
                    $subscription->next_charge_at?->format('Y-m-d H:i:s'),
                    $subscription->cancelled_at?->format('Y-m-d H:i:s'),
                    $subscription->cancel_reason,
                    $subscription->created_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function show(DonationSubscription $subscription): Response
    {
        $subscription->load([
            'cause',
            'package',
            'donor',
            'donationOrders' => function ($query): void {
                $query->with('items')
                    ->orderByDesc('billing_cycle_number')
                    ->orderByDesc('created_at');
            },
        ]);

        return Inertia::render('Admin/Subscriptions/Show', [
            'subscription' => AdminInertiaResources::subscriptionDetail($subscription),
            'orders' => $subscription->donationOrders
                ->map(fn (DonationOrder $order) => AdminInertiaResources::subscriptionOrderRow($order))
                ->values()
                ->all(),
        ]);
    }

    public function cancel(CancelSubscriptionRequest $request, DonationSubscription $subscription): RedirectResponse
    {
        if (! $subscription->canBeCancelled()) {
            return back()->withErrors([
                'cancel' => 'This subscription is already cancelled or completed.',
            ]);
        }

        try {
            $this->subscriptionService->cancelSubscription(
                $subscription,
                $request->input('cancel_reason'),
                $request->boolean('cancel_at_cycle_end')
            );
        } catch (Throwable $exception) {
            return back()->withErrors([
                'cancel' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.subscriptions.show', $subscription)
            ->with('status', 'Subscription cancelled successfully.');
    }

    public function sync(DonationSubscription $subscription): RedirectResponse
    {
        if (! $subscription->razorpay_subscription_id) {
            return back()->withErrors([
                'sync' => 'This subscription is not linked to Razorpay.',
            ]);
        }

        try {
            $this->subscriptionService->syncSubscriptionFromRazorpay($subscription);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'sync' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.subscriptions.show', $subscription)
            ->with('status', 'Subscription status synced from Razorpay.');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = DonationSubscription::query()->search($request->input('search'));

        if ($request->filled('cause_id')) {
            $query->where('cause_id', (int) $request->input('cause_id'));
        }

        if ($request->filled('package_id')) {
            $query->where('cause_package_id', (int) $request->input('package_id'));
        }

        if ($request->filled('cause_title')) {
            $this->applyCauseTitleFilter($query, trim((string) $request->input('cause_title')));
        }

        if ($request->filled('source')) {
            DonationAttributionService::applyTrafficSourceFilter($query, (string) $request->input('source'));
        }

        if ($request->filled('platform')) {
            DonationAttributionService::applyPlatformFilter($query, (string) $request->input('platform'));
        }

        if ($request->filled('utm_campaign')) {
            $query->where('utm_campaign', (string) $request->input('utm_campaign'));
        }

        if ($request->filled('utm_content')) {
            $query->where('utm_content', (string) $request->input('utm_content'));
        }

        $status = (string) $request->input('status', 'live');

        if ($status === 'live') {
            $query->live();
        } elseif ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query;
    }

    private function applyCauseTitleFilter(Builder $query, string $title): void
    {
        $title = trim($title);
        if ($title === '') {
            return;
        }

        $variants = collect([
            $title,
            trim((string) preg_replace('/\s*\([^)]*\)/', '', $title)),
        ])
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->unique()
            ->values();

        $query->where(function (Builder $inner) use ($variants): void {
            foreach ($variants as $index => $variant) {
                $like = '%'.addcslashes($variant, '%_\\').'%';
                $method = $index === 0 ? 'where' : 'orWhere';

                $inner->{$method}(function (Builder $match) use ($variant, $like): void {
                    $match
                        ->where('item_title', $variant)
                        ->orWhere('item_title', 'like', $like)
                        ->orWhereHas('package', fn (Builder $packageQuery) => $packageQuery
                            ->where('title', $variant)
                            ->orWhere('title', 'like', $like));
                });
            }
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSubscriptionsListSort(Request $request): array
    {
        $sort = (string) $request->input('sort', 'created_at_ts');
        $allowed = [
            'donor_name',
            'cause',
            'total_amount',
            'frequency_label',
            'status',
            'billing_cycle_count',
            'next_charge_at',
            'created_at_ts',
            'created_at',
        ];

        if (! in_array($sort, $allowed, true)) {
            $sort = 'created_at_ts';
        }

        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        return [$sort, $dir];
    }

    private function applySubscriptionsListSort(Builder $query, string $sort, string $dir): void
    {
        if ($sort === 'cause') {
            $query->orderBy(
                Cause::query()
                    ->select('title')
                    ->whereColumn('causes.id', 'donation_subscriptions.cause_id')
                    ->limit(1),
                $dir
            )->orderBy('donation_subscriptions.id', $dir);

            return;
        }

        $column = match ($sort) {
            'donor_name' => 'donor_name',
            'total_amount' => 'total_amount',
            'frequency_label' => 'frequency',
            'status' => 'status',
            'billing_cycle_count' => 'billing_cycle_count',
            'next_charge_at' => 'next_charge_at',
            default => 'created_at',
        };

        $query->orderBy($column, $dir)->orderBy('id', $dir);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $statusCounts
     * @return list<array{key: string, label: string, count: int}>
     */
    private function statusTabs($statusCounts): array
    {
        $total = (int) $statusCounts->sum();

        return [
            ['key' => 'live', 'label' => 'Live', 'count' => DonationSubscription::query()->live()->count()],
            ['key' => 'all', 'label' => 'All', 'count' => $total],
            ['key' => DonationSubscription::STATUS_CREATED, 'label' => 'Incomplete', 'count' => (int) ($statusCounts[DonationSubscription::STATUS_CREATED] ?? 0)],
            ['key' => DonationSubscription::STATUS_ACTIVE, 'label' => 'Active', 'count' => (int) ($statusCounts[DonationSubscription::STATUS_ACTIVE] ?? 0)],
            ['key' => DonationSubscription::STATUS_AUTHENTICATED, 'label' => 'Authenticated', 'count' => (int) ($statusCounts[DonationSubscription::STATUS_AUTHENTICATED] ?? 0)],
            ['key' => DonationSubscription::STATUS_PENDING, 'label' => 'Pending', 'count' => (int) ($statusCounts[DonationSubscription::STATUS_PENDING] ?? 0)],
            ['key' => DonationSubscription::STATUS_HALTED, 'label' => 'Halted', 'count' => (int) ($statusCounts[DonationSubscription::STATUS_HALTED] ?? 0)],
            ['key' => DonationSubscription::STATUS_CANCELLED, 'label' => 'Cancelled', 'count' => (int) ($statusCounts[DonationSubscription::STATUS_CANCELLED] ?? 0)],
            ['key' => DonationSubscription::STATUS_COMPLETED, 'label' => 'Completed', 'count' => (int) ($statusCounts[DonationSubscription::STATUS_COMPLETED] ?? 0)],
        ];
    }
}
