<?php

namespace App\Support\Meta;

use App\Models\MetaCapiEventLog;
use App\Models\MetaPixel;
use App\Models\User;
use App\Services\Meta\MetaConversionsApiService;
use App\Support\MarketerNameMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MetaCapiEventLogQuery
{
    /**
     * @return array{
     *     meta_pixel_id: string,
     *     event_name: string,
     *     partner_user_id: string,
     *     from_date: string,
     *     to_date: string,
     *     status: string
     * }
     */
    public static function filtersFromRequest(Request $request): array
    {
        return [
            'meta_pixel_id' => trim((string) $request->input('meta_pixel_id', '')),
            'event_name' => trim((string) $request->input('event_name', '')),
            'partner_user_id' => trim((string) $request->input('partner_user_id', '')),
            'from_date' => trim((string) $request->input('from_date', '')),
            'to_date' => trim((string) $request->input('to_date', '')),
            'status' => trim((string) $request->input('status', '')),
        ];
    }

    /**
     * @param  Builder<MetaCapiEventLog>  $query
     * @param  array<string, string>  $filters
     * @return Builder<MetaCapiEventLog>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        if ($filters['meta_pixel_id'] !== '') {
            $query->where('meta_pixel_id', (int) $filters['meta_pixel_id']);
        }

        if ($filters['event_name'] !== '') {
            $query->where('event_name', $filters['event_name']);
        }

        if ($filters['status'] !== '' && in_array($filters['status'], [
            MetaCapiEventLog::STATUS_SUCCESS,
            MetaCapiEventLog::STATUS_ERROR,
        ], true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['partner_user_id'] !== '') {
            $partnerUserId = (int) $filters['partner_user_id'];
            $referralCode = User::query()
                ->whereKey($partnerUserId)
                ->value('referral_code');

            $query->whereHas('donationOrder', function (Builder $orderQuery) use ($partnerUserId, $referralCode): void {
                $orderQuery->where(function (Builder $inner) use ($partnerUserId, $referralCode): void {
                    $inner->where('partner_user_id', $partnerUserId);

                    if (filled($referralCode)) {
                        $inner->orWhere('partner_code', (string) $referralCode);
                    }
                });
            });
        }

        $timezone = config('app.timezone');

        if ($filters['from_date'] !== '') {
            $from = Carbon::parse($filters['from_date'], $timezone)->startOfDay();
            $query->where(function (Builder $inner) use ($from): void {
                $inner->where('sent_at', '>=', $from)
                    ->orWhere(function (Builder $fallback) use ($from): void {
                        $fallback->whereNull('sent_at')
                            ->where('created_at', '>=', $from);
                    });
            });
        }

        if ($filters['to_date'] !== '') {
            $to = Carbon::parse($filters['to_date'], $timezone)->endOfDay();
            $query->where(function (Builder $inner) use ($to): void {
                $inner->where('sent_at', '<=', $to)
                    ->orWhere(function (Builder $fallback) use ($to): void {
                        $fallback->whereNull('sent_at')
                            ->where('created_at', '<=', $to);
                    });
            });
        }

        return $query;
    }

    /**
     * @return array{
     *     pixels: list<array{id: int, label: string, pixel_id: string}>,
     *     events: list<array{value: string, label: string}>,
     *     marketers: list<array{id: int, name: string, code: string}>,
     *     statuses: list<array{value: string, label: string}>
     * }
     */
    public static function filterOptions(): array
    {
        $pixels = MetaPixel::query()
            ->orderBy('label')
            ->get(['id', 'label', 'pixel_id'])
            ->map(fn (MetaPixel $pixel) => [
                'id' => $pixel->id,
                'label' => $pixel->label,
                'pixel_id' => $pixel->pixel_id,
            ])
            ->values()
            ->all();

        return [
            'pixels' => $pixels,
            'events' => [
                ['value' => MetaConversionsApiService::EVENT_PURCHASE, 'label' => 'Purchase'],
                ['value' => MetaConversionsApiService::EVENT_INITIATE_CHECKOUT, 'label' => 'InitiateCheckout'],
            ],
            'marketers' => MarketerNameMatcher::marketerOptions()->values()->all(),
            'statuses' => [
                ['value' => MetaCapiEventLog::STATUS_SUCCESS, 'label' => 'Success'],
                ['value' => MetaCapiEventLog::STATUS_ERROR, 'label' => 'Error'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeLog(MetaCapiEventLog $log): array
    {
        $order = $log->donationOrder;
        $partner = $order?->partner;
        $sid = filled($order?->partner_code) ? (string) $order->partner_code : null;
        $partnerName = $partner?->name;

        if ($partnerName === null && $sid !== null) {
            $partnerName = User::query()
                ->where('referral_code', $sid)
                ->value('name');
        }

        $timezone = config('app.timezone');
        $when = $log->sent_at ?? $log->created_at;

        return [
            'id' => $log->id,
            'pixel_label' => $log->pixel?->label,
            'pixel_id' => $log->pixel?->pixel_id,
            'meta_pixel_id' => $log->meta_pixel_id,
            'event_name' => $log->event_name,
            'event_id' => $log->event_id,
            'status' => $log->status,
            'http_status' => $log->http_status,
            'error_message' => $log->error_message,
            'order_uuid' => $order?->order_uuid,
            'donation_order_id' => $log->donation_order_id,
            'sid' => $sid,
            'partner_name' => $partnerName,
            'partner_user_id' => $order?->partner_user_id,
            'sent_at' => $when?->timezone($timezone)->toDateTimeString(),
        ];
    }
}
