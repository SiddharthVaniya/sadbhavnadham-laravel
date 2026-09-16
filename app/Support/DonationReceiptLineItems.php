<?php

namespace App\Support;

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use Illuminate\Support\Collection;

class DonationReceiptLineItems
{
    /**
     * @return Collection<int, object{
     *     cause_title: string,
     *     package_title: ?string,
     *     honoree_names: array<int, string>,
     *     unit_amount: float|int,
     *     quantity: int|string,
     *     amount: float|int
     * }>
     */
    public static function forOrder(DonationOrder $order): Collection
    {
        $order->loadMissing(['items.causeModel', 'items.package']);

        if ($order->items->isEmpty()) {
            return collect([(object) [
                'cause_title' => 'Donation',
                'package_title' => null,
                'honoree_names' => [],
                'unit_amount' => $order->total_amount,
                'quantity' => 1,
                'amount' => $order->total_amount,
            ]]);
        }

        return $order->items
            ->flatMap(fn (DonationItem $item) => self::linesForItem($item))
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    private static function linesForItem(DonationItem $item): Collection
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $dailyNeedsSummary = trim((string) ($meta['daily_needs_summary'] ?? ''));
        $fallbackTitle = $dailyNeedsSummary !== '' ? $dailyNeedsSummary : (string) $item->title;

        if (self::isDailyNeedsItem($item, $meta, $fallbackTitle)) {
            $lines = AdminInertiaResources::dailyNeedsLinesFromMeta($meta, $fallbackTitle);

            if ($lines !== []) {
                return collect($lines)->map(fn (array $line) => (object) [
                    'cause_title' => trim((string) ($line['title'] ?? '')) !== ''
                        ? (string) $line['title']
                        : 'Daily Need',
                    'package_title' => null,
                    'honoree_names' => [],
                    'unit_amount' => (float) ($line['unit_price'] ?? 0),
                    'quantity' => self::dailyNeedsQuantityLabel($line),
                    'amount' => (float) ($line['amount'] ?? 0),
                ]);
            }
        }

        return collect([(object) self::standardLineFromItem($item)]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function isDailyNeedsItem(DonationItem $item, array $meta, string $fallbackTitle): bool
    {
        $slug = $item->causeModel?->slug ?? ($meta['cause_slug'] ?? $item->cause);

        return $slug === Cause::SLUG_DAILY_NEEDS
            || trim((string) ($meta['daily_needs_summary'] ?? '')) !== ''
            || AdminInertiaResources::looksLikeDailyNeedsSource($fallbackTitle);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function dailyNeedsQuantityLabel(array $line): string|int
    {
        $qtyLabel = trim((string) ($line['qty_label'] ?? ''));
        if ($qtyLabel !== '') {
            return $qtyLabel;
        }

        $qty = max(1, (int) ($line['qty'] ?? 1));
        $unit = trim((string) ($line['unit'] ?? ''));

        return $unit !== '' ? $qty.$unit : $qty;
    }

    /**
     * @return array{
     *     cause_title: string,
     *     package_title: ?string,
     *     honoree_names: array<int, string>,
     *     unit_amount: float|int,
     *     quantity: int,
     *     amount: float|int
     * }
     */
    private static function standardLineFromItem(DonationItem $item): array
    {
        $meta = is_array($item->meta) ? $item->meta : [];

        $causeTitle = $item->causeModel?->title
            ?: ($meta['cause_title'] ?? null)
            ?: $item->title
            ?: 'Donation';
        $packageTitle = $item->package?->title
            ?: ($meta['package_title'] ?? null);

        return [
            'cause_title' => (string) $causeTitle,
            'package_title' => filled($packageTitle) ? (string) $packageTitle : null,
            'honoree_names' => TreeDedication::displayLines($meta['honoree_names'] ?? null),
            'unit_amount' => $item->unit_amount,
            'quantity' => max(1, (int) $item->quantity),
            'amount' => $item->amount,
        ];
    }
}
