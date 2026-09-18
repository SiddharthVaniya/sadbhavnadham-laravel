<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckoutRecoveryIndexRequest;
use App\Http\Requests\Admin\CheckoutRecoveryNudgeRequest;
use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\NotifyPaymentLinkJob;
use App\Jobs\SendPaymentLinkWhatsAppJob;
use App\Models\DonationOrder;
use App\Services\DonationWhatsAppPolicy;
use App\Services\RazorpayPaymentLinkService;
use App\Support\AdminInertiaData;
use App\Support\DonationVisibility;
use App\Support\PeriodRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminCheckoutRecoveryController extends Controller
{
    public function __construct(
        private DonationWhatsAppPolicy $donationWhatsAppPolicy,
    ) {}

    public function index(CheckoutRecoveryIndexRequest $request): Response
    {
        abort_unless(DonationVisibility::userCanViewAll($request->user()), 403);

        $duration = (string) $request->input('duration', 'last_7_days');
        $query = $this->recoveryQuery($request, $duration);

        $overviewBase = clone $query;
        $totalCount = (clone $overviewBase)->count();
        $totalAmount = (float) (clone $overviewBase)->sum('total_amount');
        $pendingCount = (clone $overviewBase)->where('status', DonationOrder::STATUS_PENDING)->count();
        $failedCount = (clone $overviewBase)->where('status', DonationOrder::STATUS_FAILED)->count();
        $awaitingNudgeCount = (clone $overviewBase)->whereNull('payment_link_sent_at')->count();

        $orders = $query
            ->with(['items.causeModel'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $activeFilterCount = collect([
            $request->filled('status'),
            $request->filled('search'),
            $request->filled('nudge'),
            $duration !== 'last_7_days',
        ])->filter()->count();

        return Inertia::render('Admin/CheckoutRecovery/Index', [
            'orders' => AdminInertiaData::paginatedRecoveryOrders($orders),
            'duration' => $duration,
            'durationOptions' => [
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'this_week' => 'This week',
                'last_week' => 'Previous week',
                'this_month' => 'This month',
                'last_month' => 'Previous month',
                'last_7_days' => 'Last 7 days',
                'last_30_days' => 'Last 30 days',
                'last_90_days' => 'Last 90 days',
                'all' => 'All time',
            ],
            'stats' => [
                'total_count' => $totalCount,
                'total_amount' => $totalAmount,
                'pending_count' => $pendingCount,
                'failed_count' => $failedCount,
                'ready_count' => $awaitingNudgeCount,
            ],
            'activeFilterCount' => $activeFilterCount,
            'filters' => [
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
                'nudge' => $request->input('nudge', ''),
            ],
            'nudgeUrl' => route('admin.donations.recovery.nudge'),
            'canNudge' => $request->user()?->can('manage receipts') ?? false,
        ]);
    }

    public function nudge(CheckoutRecoveryNudgeRequest $request): RedirectResponse
    {
        $channel = (string) $request->validated('channel', 'whatsapp');
        $paymentLinks = app(RazorpayPaymentLinkService::class);

        $orders = DonationOrder::query()
            ->abandonedCheckouts()
            ->whereIn('id', $request->validated('order_ids'))
            ->get();

        $orders = $orders->filter(
            fn (DonationOrder $order) => DonationVisibility::canView($request->user(), $order)
        );

        $queued = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $canChannel = match ($channel) {
                RazorpayPaymentLinkService::MEDIUM_EMAIL => $paymentLinks->hasSendableEmail($order->donor_email),
                RazorpayPaymentLinkService::MEDIUM_SMS,
                'whatsapp' => $this->donationWhatsAppPolicy->hasSendablePhoneNumber($order->donor_phone),
                default => false,
            };

            if (! $canChannel) {
                $skipped++;

                continue;
            }

            if ($order->isPending()) {
                $order->markAsFailed();
                $order->refresh();
            }

            if (! $order->isFailed()) {
                $skipped++;

                continue;
            }

            if ($channel === 'whatsapp') {
                if (! filled($order->payment_link_url)) {
                    CreatePaymentLinkJob::dispatch($order->id, true);
                } else {
                    SendPaymentLinkWhatsAppJob::dispatch($order->id, true);
                }
            } else {
                NotifyPaymentLinkJob::dispatch($order->id, $channel);
            }

            $queued++;
        }

        if ($queued === 0) {
            $message = $skipped > 0
                ? 'No payment-link nudges were queued. Check donor contact details and checkout status.'
                : 'No matching abandoned checkouts were found.';

            toastr()->warning($message);

            return redirect()
                ->route('admin.donations.recovery')
                ->with('status', $message)
                ->with('flash_tone', 'warning');
        }

        $channelLabel = match ($channel) {
            RazorpayPaymentLinkService::MEDIUM_EMAIL => 'email',
            RazorpayPaymentLinkService::MEDIUM_SMS => 'SMS',
            default => 'WhatsApp',
        };

        $message = $queued === 1
            ? "Payment-link {$channelLabel} nudge queued for 1 checkout."
            : "Payment-link {$channelLabel} nudges queued for {$queued} checkouts.";

        if ($skipped > 0) {
            $message .= " Skipped {$skipped}.";
        }

        toastr()->success($message);

        return redirect()
            ->route('admin.donations.recovery')
            ->with('status', $message);
    }

    private function recoveryQuery(CheckoutRecoveryIndexRequest $request, string $duration): Builder
    {
        $query = DonationOrder::query()
            ->abandonedCheckouts()
            ->where('created_at', '<=', now()->subMinutes(5));

        DonationVisibility::apply($query, $request->user());

        $this->applyDurationFilter($query, $duration);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('donor_name', 'like', "%{$search}%")
                    ->orWhere('donor_email', 'like', "%{$search}%")
                    ->orWhere('donor_phone', 'like', "%{$search}%")
                    ->orWhere('provider_order_id', 'like', "%{$search}%")
                    ->orWhere('order_uuid', 'like', "%{$search}%");
            });
        }

        if ($request->input('nudge') === 'sent') {
            $query->whereNotNull('payment_link_sent_at');
        } elseif ($request->input('nudge') === 'ready') {
            $query->whereNull('payment_link_sent_at');
        } elseif ($request->input('nudge') === 'no_phone') {
            $query->where(function (Builder $builder) {
                $builder->whereNull('donor_phone')
                    ->orWhere('donor_phone', '');
            });
        }

        return $query;
    }

    private function applyDurationFilter(Builder $query, string $duration): void
    {
        $now = Carbon::now();

        if ($duration === 'today') {
            $query->whereBetween('created_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);

            return;
        }

        if ($calendar = PeriodRange::forKey($duration)) {
            $query->whereBetween('created_at', [$calendar['start'], $calendar['end']]);

            return;
        }

        if ($duration === 'last_7_days') {
            $query->where('created_at', '>=', $now->copy()->subDays(6)->startOfDay());

            return;
        }

        if ($duration === 'last_30_days') {
            $query->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay());

            return;
        }

        if ($duration === 'last_90_days') {
            $query->where('created_at', '>=', $now->copy()->subDays(89)->startOfDay());
        }
    }
}
