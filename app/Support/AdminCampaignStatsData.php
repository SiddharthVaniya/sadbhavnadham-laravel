<?php

namespace App\Support;

use App\Models\AnalyticsEvent;
use App\Models\DonationCampaign;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminCampaignStatsData
{
    /**
     * @return array<string, mixed>
     */
    public static function reportForCampaign(DonationCampaign $campaign, Request $request): array
    {
        $duration = (string) $request->input('duration', 'all');

        if (! array_key_exists($duration, AdminAnalyticsData::DURATION_OPTIONS)) {
            $duration = 'all';
        }

        $range = AdminAnalyticsData::resolveRange($request, $duration);
        $metrics = self::metricsForCampaign($campaign, $range['start'], $range['end']);

        return [
            'campaign' => AdminInertiaResources::donationCampaign($campaign),
            'duration' => $duration,
            'durationOptions' => AdminAnalyticsData::DURATION_OPTIONS,
            'durationLabel' => $range['label'],
            'filters' => [
                'from_date' => $request->filled('from_date') ? (string) $request->input('from_date') : null,
                'to_date' => $request->filled('to_date') ? (string) $request->input('to_date') : null,
            ],
            'summary' => $metrics,
            'activeSubscribers' => self::activeSubscribersForCampaign($campaign),
            'recentDonations' => self::recentDonationsForCampaign($campaign, $range['start'], $range['end']),
            'multiCampaignDonors' => self::donorsWithMultipleLiveCampaigns(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function metricsForCampaign(
        DonationCampaign $campaign,
        ?Carbon $start = null,
        ?Carbon $end = null,
    ): array {
        $paidQuery = self::paidItemsQuery($campaign->id, $start, $end);

        $paidCount = (int) (clone $paidQuery)->count();
        $revenue = (float) (clone $paidQuery)->sum('donation_items.amount');

        $oneTimeCount = (int) (clone $paidQuery)
            ->where(function (Builder $query): void {
                $query->where('donation_orders.is_recurring', false)
                    ->orWhereNull('donation_orders.donation_subscription_id');
            })
            ->count();

        $recurringRevenue = (float) (clone $paidQuery)
            ->where('donation_orders.is_recurring', true)
            ->sum('donation_items.amount');

        $subscriptionsStarted = self::subscriptionsQuery($campaign->id, $start, $end)->count();
        $activeSubscriptions = self::subscriptionsQuery($campaign->id)->live()->count();
        $cancelledSubscriptions = self::subscriptionsQuery($campaign->id, $start, $end)
            ->whereIn('status', [
                DonationSubscription::STATUS_CANCELLED,
                DonationSubscription::STATUS_COMPLETED,
            ])
            ->count();

        $pageViews = self::pageViewsForCampaign($campaign, $start, $end);
        $goalAmount = $campaign->goal_amount !== null ? (float) $campaign->goal_amount : null;
        $goalProgressPercent = $goalAmount && $goalAmount > 0
            ? min(100, round(($revenue / $goalAmount) * 100, 1))
            : null;

        return [
            'page_views' => $pageViews,
            'paid_count' => $paidCount,
            'one_time_count' => $oneTimeCount,
            'recurring_paid_count' => max(0, $paidCount - $oneTimeCount),
            'revenue' => round($revenue, 2),
            'one_time_revenue' => round($revenue - $recurringRevenue, 2),
            'recurring_revenue' => round($recurringRevenue, 2),
            'subscriptions_started' => $subscriptionsStarted,
            'active_subscriptions' => $activeSubscriptions,
            'cancelled_subscriptions' => $cancelledSubscriptions,
            'goal_amount' => $goalAmount,
            'goal_progress_percent' => $goalProgressPercent,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function activeSubscribersForCampaign(DonationCampaign $campaign): array
    {
        return self::subscriptionsQuery($campaign->id)
            ->live()
            ->with('cause:id,title')
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (DonationSubscription $subscription): array => [
                'donor_name' => $subscription->donor_name,
                'donor_email' => $subscription->donor_email,
                'donor_phone' => $subscription->donor_phone,
                'amount' => (float) $subscription->total_amount,
                'frequency' => $subscription->frequencyLabel(),
                'status' => $subscription->statusLabel(),
                'started_at' => $subscription->started_at?->format('d M Y') ?? '—',
                'next_charge_at' => $subscription->next_charge_at?->format('d M Y') ?? '—',
                'subscription_url' => route('admin.subscriptions.show', $subscription),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recentDonationsForCampaign(
        DonationCampaign $campaign,
        ?Carbon $start = null,
        ?Carbon $end = null,
        int $limit = 25,
    ): array {
        return self::paidItemsQuery($campaign->id, $start, $end)
            ->with('order:id,order_uuid,paid_at,is_recurring')
            ->orderByDesc('donation_orders.paid_at')
            ->limit($limit)
            ->get()
            ->map(fn (DonationItem $item): array => [
                'donor_name' => $item->order?->donor_name ?? '—',
                'title' => $item->title,
                'amount' => (float) $item->amount,
                'paid_at' => $item->order?->paid_at?->format('d M Y, h:i A') ?? '—',
                'is_recurring' => (bool) ($item->order?->is_recurring ?? false),
                'donation_url' => $item->order
                    ? route('admin.donations.show', $item->order)
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function donorsWithMultipleLiveCampaigns(): array
    {
        $subscriptions = DonationSubscription::query()
            ->live()
            ->where(function (Builder $query): void {
                $query->whereNotNull('donation_campaign_id')
                    ->orWhereNotNull('meta->campaign->id');
            })
            ->get();

        return $subscriptions
            ->groupBy(fn (DonationSubscription $subscription): string => strtolower(trim((string) $subscription->donor_email)))
            ->map(function (Collection $group): ?array {
                $campaigns = $group
                    ->map(function (DonationSubscription $subscription): ?array {
                        $campaignId = $subscription->donation_campaign_id
                            ?? ($subscription->meta['campaign']['id'] ?? null);

                        if (! is_numeric($campaignId)) {
                            return null;
                        }

                        return [
                            'id' => (int) $campaignId,
                            'name' => (string) ($subscription->meta['campaign']['name'] ?? 'Campaign #'.$campaignId),
                            'amount' => (float) $subscription->total_amount,
                            'status' => $subscription->statusLabel(),
                            'stats_url' => route('admin.campaigns.show', (int) $campaignId),
                        ];
                    })
                    ->filter()
                    ->unique('id')
                    ->values();

                if ($campaigns->count() < 2) {
                    return null;
                }

                $first = $group->first();

                return [
                    'donor_name' => $first?->donor_name ?? '—',
                    'donor_email' => $first?->donor_email ?? '—',
                    'donor_phone' => $first?->donor_phone ?? '—',
                    'campaigns' => $campaigns->all(),
                ];
            })
            ->filter()
            ->values()
            ->sortBy('donor_name')
            ->values()
            ->all();
    }

    public static function pageViewsForCampaign(
        DonationCampaign $campaign,
        ?Carbon $start = null,
        ?Carbon $end = null,
    ): int {
        $query = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::TYPE_VISIT_CAMPAIGN)
            ->where('path', '/give/'.$campaign->slug);

        if ($start) {
            $query->where('created_at', '>=', $start);
        }

        if ($end) {
            $query->where('created_at', '<=', $end);
        }

        return (int) $query->count();
    }

    /**
     * @param  Builder<DonationItem>  $query
     */
    public static function applyCampaignScope(Builder $query, int $campaignId, string $table = 'donation_items'): void
    {
        $query->where(function (Builder $inner) use ($campaignId, $table): void {
            $inner->where("{$table}.donation_campaign_id", $campaignId)
                ->orWhere("{$table}.meta->campaign->id", $campaignId);
        });
    }

    /**
     * @return Builder<DonationItem>
     */
    private static function paidItemsQuery(int $campaignId, ?Carbon $start = null, ?Carbon $end = null): Builder
    {
        $query = DonationItem::query()
            ->join('donation_orders', 'donation_orders.id', '=', 'donation_items.donation_order_id')
            ->where('donation_orders.status', DonationOrder::STATUS_PAID)
            ->select('donation_items.*');

        self::applyCampaignScope($query, $campaignId);

        if ($start) {
            $query->where('donation_orders.paid_at', '>=', $start);
        }

        if ($end) {
            $query->where('donation_orders.paid_at', '<=', $end);
        }

        return $query;
    }

    /**
     * @return Builder<DonationSubscription>
     */
    private static function subscriptionsQuery(
        int $campaignId,
        ?Carbon $start = null,
        ?Carbon $end = null,
    ): Builder {
        $query = DonationSubscription::query()
            ->where(function (Builder $inner) use ($campaignId): void {
                $inner->where('donation_campaign_id', $campaignId)
                    ->orWhere('meta->campaign->id', $campaignId);
            });

        if ($start) {
            $query->where('created_at', '>=', $start);
        }

        if ($end) {
            $query->where('created_at', '<=', $end);
        }

        return $query;
    }
}
