<?php

namespace App\Support;

use App\Models\DonationOrder;
use App\Models\Donor;
use App\Services\DonationAttributionService;
use App\Services\DonationWhatsAppPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DonorAudienceQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['city'])) {
            $query->where('city', (string) $filters['city']);
        }

        if (! empty($filters['state'])) {
            $query->where('state', (string) $filters['state']);
        }

        if (! empty($filters['source'])) {
            $source = (string) $filters['source'];

            $query->whereHas('donationOrders', function (Builder $orders) use ($source): void {
                $orders->where('status', DonationOrder::STATUS_PAID);
                DonationAttributionService::applyTrafficSourceFilter($orders, $source);
            });
        }

        if (! empty($filters['from_date']) || ! empty($filters['to_date'])) {
            $query->whereHas('paidDonationOrders', function (Builder $orders) use ($filters): void {
                if (! empty($filters['from_date'])) {
                    $orders->where(
                        'paid_at',
                        '>=',
                        Carbon::parse((string) $filters['from_date'])->startOfDay()
                    );
                }

                if (! empty($filters['to_date'])) {
                    $orders->where(
                        'paid_at',
                        '<=',
                        Carbon::parse((string) $filters['to_date'])->endOfDay()
                    );
                }
            });
        }

        if (isset($filters['min_paid']) && is_numeric($filters['min_paid'])) {
            $minPaid = (float) $filters['min_paid'];

            $query->whereIn('id', function ($subQuery) use ($minPaid): void {
                $subQuery->select('donor_id')
                    ->from('donation_orders')
                    ->where('status', DonationOrder::STATUS_PAID)
                    ->whereNotNull('donor_id')
                    ->groupBy('donor_id')
                    ->havingRaw('SUM(total_amount) >= ?', [$minPaid]);
            });
        }

        if (! empty($filters['repeat'])) {
            $query->whereIn('id', function ($subQuery): void {
                $subQuery->select('donor_id')
                    ->from('donation_orders')
                    ->where('status', DonationOrder::STATUS_PAID)
                    ->whereNotNull('donor_id')
                    ->groupBy('donor_id')
                    ->havingRaw('COUNT(*) >= 2');
            });
        }

        if (! empty($filters['utm_campaign'])) {
            $campaign = (string) $filters['utm_campaign'];

            $query->whereHas('paidDonationOrders', function (Builder $orders) use ($campaign): void {
                $orders->where('utm_campaign', $campaign);
            });
        }

        if (! empty($filters['utm_content'])) {
            $content = (string) $filters['utm_content'];

            $query->whereHas('paidDonationOrders', function (Builder $orders) use ($content): void {
                $orders->where('utm_content', $content);
            });
        }

        if (! empty($filters['cause_id']) && is_numeric($filters['cause_id'])) {
            $causeId = (int) $filters['cause_id'];

            $query->whereHas('paidDonationOrders.items', function (Builder $items) use ($causeId): void {
                $items->where('cause_id', $causeId);
            });
        }

        return $query;
    }

    public static function fromRequest(Request $request): Builder
    {
        return self::apply(Donor::query(), [
            'search' => $request->input('search'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'source' => $request->input('source'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'min_paid' => $request->input('min_paid'),
            'repeat' => $request->boolean('repeat') ? 1 : null,
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_content' => $request->input('utm_content'),
            'cause_id' => $request->input('cause_id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<\App\Models\Donor>
     */
    public static function campaignEligible(array $filters): Builder
    {
        return self::apply(Donor::query(), $filters)
            ->where(function (Builder $optOut): void {
                $optOut->where('whatsapp_opt_out', false)->orWhereNull('whatsapp_opt_out');
            })
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id');
    }

    public static function isSendableDonor(Donor $donor): bool
    {
        if ($donor->whatsapp_opt_out) {
            return false;
        }

        return app(DonationWhatsAppPolicy::class)->hasSendablePhoneNumber($donor->phone);
    }
}
