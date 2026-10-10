<?php

namespace App\Support;

use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use App\Services\DonationAttributionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DonationOrderListQuery
{
    public function filtered(Request $request, string $duration = 'all'): Builder
    {
        $query = DonationOrder::query()->with(['items.causeModel', 'items.package', 'subscription', 'partner']);

        DonationVisibility::apply($query, $request->user());

        if ($duration === 'custom') {
            $this->applyCreatedAtDateRange($query, $request->input('from_date'), $request->input('to_date'));
        } else {
            $this->applyDurationFilter($query, $duration);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('min_amount') && is_numeric($request->input('min_amount'))) {
            $query->where('total_amount', '>=', (float) $request->input('min_amount'));
        }

        if ($request->filled('max_amount') && is_numeric($request->input('max_amount'))) {
            $query->where('total_amount', '<=', (float) $request->input('max_amount'));
        }

        if ($request->filled('provider')) {
            $this->applyProviderFilter($query, (string) $request->provider);
        }

        if ($request->filled('cause_id')) {
            $causeId = (int) $request->input('cause_id');
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('cause_id', $causeId));
        }

        if ($request->filled('package_id')) {
            $packageId = (int) $request->input('package_id');
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('cause_package_id', $packageId));
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

        if ($request->filled('partner_user_id')) {
            $partner = User::query()->find((int) $request->input('partner_user_id'));

            if ($partner) {
                AdminStaffReferralsData::applyPartnerAttributionFilter($query, $partner);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if ($this->hasSearchTerm($request)) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('donor_name', 'like', "%{$search}%")
                    ->orWhere('donor_email', 'like', "%{$search}%")
                    ->orWhere('donor_phone', 'like', "%{$search}%")
                    ->orWhere('provider_order_id', 'like', "%{$search}%")
                    ->orWhere('provider_payment_id', 'like', "%{$search}%")
                    ->orWhere('receipt_number', 'like', "%{$search}%")
                    ->orWhere('utm_source', 'like', "%{$search}%")
                    ->orWhere('utm_campaign', 'like', "%{$search}%")
                    ->orWhere('utm_content', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Same filters as the donations index, prepared for CSV streaming.
     *
     * List sort clauses must not be applied: Laravel's chunkById/forPageAfterId
     * only removes ORDER BY for the exact chunk column, so leftover sorts
     * (created_at, cause subqueries, etc.) skip or duplicate rows across chunks.
     */
    public function exportQuery(Request $request, string $duration = 'all'): Builder
    {
        return $this->filtered($request, $duration)->reorder();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function resolveSort(Request $request): array
    {
        $sort = (string) $request->input('sort', 'created_at_ts');
        $allowed = [
            'payment_id',
            'donor_name',
            'source',
            'cause',
            'cause_title',
            'total_amount',
            'status',
            'city',
            'created_at_ts',
            'created_at',
        ];

        if (! in_array($sort, $allowed, true)) {
            $sort = 'created_at_ts';
        }

        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        return [$sort, $dir];
    }

    public function applySort(Builder $query, string $sort, string $dir): void
    {
        if ($sort === 'payment_id') {
            $query->orderByRaw('COALESCE(provider_payment_id, provider_order_id) '.$dir)
                ->orderBy('id', $dir);

            return;
        }

        if ($sort === 'cause') {
            $query->orderBy(
                DonationItem::query()
                    ->select('causes.title')
                    ->leftJoin('causes', 'causes.id', '=', 'donation_items.cause_id')
                    ->whereColumn('donation_items.donation_order_id', 'donation_orders.id')
                    ->orderBy('donation_items.id')
                    ->limit(1),
                $dir
            )->orderBy('donation_orders.id', $dir);

            return;
        }

        if ($sort === 'cause_title') {
            $query->orderBy(
                DonationItem::query()
                    ->selectRaw('COALESCE(cause_packages.title, donation_items.title)')
                    ->leftJoin('cause_packages', 'cause_packages.id', '=', 'donation_items.cause_package_id')
                    ->whereColumn('donation_items.donation_order_id', 'donation_orders.id')
                    ->orderBy('donation_items.id')
                    ->limit(1),
                $dir
            )->orderBy('donation_orders.id', $dir);

            return;
        }

        if ($sort === 'source') {
            $query->orderByRaw('COALESCE(utm_source, \'\') '.$dir)
                ->orderBy('id', $dir);

            return;
        }

        $column = match ($sort) {
            'donor_name' => 'donor_name',
            'total_amount' => 'total_amount',
            'status' => 'status',
            'city' => 'city',
            default => 'created_at',
        };

        $query->orderBy($column, $dir)->orderBy('id', $dir);
    }

    public function resolveDuration(Request $request): string
    {
        if ($request->filled('from_date') || $request->filled('to_date')) {
            return 'custom';
        }

        $duration = (string) $request->input('duration', 'today');

        if ($this->hasSearchTerm($request) && in_array($duration, ['today', ''], true)) {
            return 'all';
        }

        return $duration;
    }

    public function hasSearchTerm(Request $request): bool
    {
        return trim((string) $request->input('search', '')) !== '';
    }

    public function applyCreatedAtDateRange(Builder $query, mixed $fromDate, mixed $toDate): void
    {
        if (! empty($fromDate)) {
            $start = Carbon::parse((string) $fromDate)->startOfDay();
            $this->applyActivityDateLowerBound($query, $start);
        }

        if (! empty($toDate)) {
            $end = Carbon::parse((string) $toDate)->endOfDay();
            $this->applyActivityDateUpperBound($query, $end);
        }
    }

    private function applyProviderFilter(Builder $query, string $provider): void
    {
        if ($provider === DonationOrder::PROVIDER_RAZORPAY) {
            $query->whereIn('payment_provider', [
                DonationOrder::PROVIDER_RAZORPAY,
                DonationOrder::PROVIDER_RAZORPAY_QR,
            ]);

            return;
        }

        $query->where('payment_provider', $provider);
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

        $query->whereHas('items', function (Builder $itemQuery) use ($variants) {
            $itemQuery->where(function (Builder $inner) use ($variants) {
                foreach ($variants as $index => $variant) {
                    $like = '%'.addcslashes($variant, '%_\\').'%';
                    $method = $index === 0 ? 'where' : 'orWhere';

                    $inner->{$method}(function (Builder $match) use ($variant, $like) {
                        $match
                            ->whereHas('package', fn (Builder $packageQuery) => $packageQuery
                                ->where('title', $variant)
                                ->orWhere('title', 'like', $like))
                            ->orWhere('title', 'like', $like)
                            ->orWhere('cause', 'like', $like)
                            ->orWhereRaw('CAST(meta AS CHAR) LIKE ?', [$like]);
                    });
                }
            });
        });
    }

    private function applyDurationFilter(Builder $query, string $duration): void
    {
        $now = now();

        if ($duration === 'today') {
            $this->applyActivityDateRange(
                $query,
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay()
            );

            return;
        }

        if ($calendar = PeriodRange::forKey($duration)) {
            $this->applyActivityDateRange($query, $calendar['start'], $calendar['end']);

            return;
        }

        if ($duration === 'last_7_days') {
            $this->applyActivityDateLowerBound($query, $now->copy()->subDays(6)->startOfDay());

            return;
        }

        if ($duration === 'last_30_days') {
            $this->applyActivityDateLowerBound($query, $now->copy()->subDays(29)->startOfDay());

            return;
        }

        if ($duration === 'last_90_days') {
            $this->applyActivityDateLowerBound($query, $now->copy()->subDays(89)->startOfDay());
        }
    }

    private function applyActivityDateRange(Builder $query, Carbon $start, Carbon $end): void
    {
        $query->whereActivityBetween($start, $end);
    }

    private function applyActivityDateLowerBound(Builder $query, Carbon $start): void
    {
        $query->whereActivityOnOrAfter($start);
    }

    private function applyActivityDateUpperBound(Builder $query, Carbon $end): void
    {
        $query->where(function (Builder $activityQuery) use ($end): void {
            $activityQuery
                ->where('paid_at', '<=', $end)
                ->orWhere(function (Builder $unpaidQuery) use ($end): void {
                    $unpaidQuery
                        ->whereNull('paid_at')
                        ->where('created_at', '<=', $end);
                });
        });
    }
}
