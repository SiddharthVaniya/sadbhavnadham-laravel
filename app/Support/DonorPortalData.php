<?php

namespace App\Support;

use App\Models\DonationItem;
use App\Models\DonationOrder;
use Illuminate\Support\Str;

class DonorPortalData
{
    /**
     * @return array{
     *     cause_title: string,
     *     item_title: string|null,
     *     headline: string,
     *     amount_label: string,
     *     date_label: string|null,
     *     receipt_label: string|null,
     *     is_recurring: bool
     * }
     */
    public static function orderCard(DonationOrder $order): array
    {
        $order->loadMissing(['items.causeModel']);

        $firstItem = $order->items->first();
        $causeTitle = $firstItem ? self::resolveCauseTitle($firstItem) : 'Donation';
        $itemTitle = self::resolveItemTitle($firstItem, $causeTitle);

        $headline = $order->items
            ->map(fn (DonationItem $item): string => self::itemHeadline($item))
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        if ($headline === '') {
            $headline = $causeTitle;
        }

        $paidAt = $order->paid_at ?? $order->created_at;

        return [
            'cause_title' => $causeTitle,
            'item_title' => $itemTitle,
            'headline' => $headline,
            'amount_label' => self::formatMoney((float) $order->total_amount),
            'date_label' => $paidAt?->timezone(config('app.timezone'))->format('d M Y'),
            'receipt_label' => $order->hasReceipt() ? $order->receiptNumberFormatted() : null,
            'is_recurring' => (bool) $order->is_recurring,
        ];
    }

    /**
     * @return array{count: int, total_label: string}
     */
    public static function summary(int $count, float $totalAmount): array
    {
        return [
            'count' => $count,
            'total_label' => self::formatMoney($totalAmount),
        ];
    }

    public static function formatMoney(float $amount): string
    {
        return '₹'.number_format($amount, 2);
    }

    public static function itemHeadline(DonationItem $item): string
    {
        if (! $item->relationLoaded('causeModel') && $item->exists) {
            $item->loadMissing('causeModel');
        }

        $causeTitle = self::resolveCauseTitle($item);
        $itemTitle = self::resolveItemTitle($item, $causeTitle);

        if ($itemTitle === null) {
            return $causeTitle;
        }

        return "{$causeTitle} · {$itemTitle}";
    }

    private static function resolveCauseTitle(DonationItem $item): string
    {
        $fromRelation = trim((string) ($item->causeModel?->title ?? ''));
        if ($fromRelation !== '') {
            return $fromRelation;
        }

        $fromMeta = trim((string) ($item->meta['cause_title'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        $fromSlug = self::humanizeCauseSlug($item->cause);
        if ($fromSlug !== null) {
            return $fromSlug;
        }

        return 'Donation';
    }

    private static function resolveItemTitle(?DonationItem $item, string $causeTitle): ?string
    {
        if ($item === null) {
            return null;
        }

        $itemTitle = trim((string) $item->title);
        if ($itemTitle === '') {
            return null;
        }

        if (self::titlesEquivalent($causeTitle, $itemTitle)) {
            return null;
        }

        $humanizedItem = self::humanizeCauseSlug($itemTitle);
        if ($humanizedItem !== null && self::titlesEquivalent($causeTitle, $humanizedItem)) {
            return null;
        }

        return $itemTitle;
    }

    private static function humanizeCauseSlug(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, '-')) {
            return Str::title(str_replace('-', ' ', $value));
        }

        if (strtolower($value) === $value && ! str_contains($value, ' ')) {
            return Str::title($value);
        }

        return $value;
    }

    private static function titlesEquivalent(string $left, string $right): bool
    {
        return strcasecmp(trim($left), trim($right)) === 0;
    }
}
