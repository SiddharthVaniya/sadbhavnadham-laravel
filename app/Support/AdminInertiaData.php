<?php

namespace App\Support;

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Services\DonationAttributionService;
use App\Services\DonationWhatsAppPolicy;
use App\Services\RazorpayPaymentLinkService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminInertiaData
{
    public static function donationRow(DonationOrder $order): array
    {
        $item = $order->items->first();
        $causeTitleLines = self::donationItemCauseTitleLines($item);

        return [
            'id' => $order->id,
            'uuid' => $order->order_uuid,
            'payment_id' => $order->provider_payment_id ?: $order->provider_order_id ?: (string) $order->id,
            'provider' => DonationOrder::providerDisplayName($order->payment_provider),
            'donor_name' => $order->donor_name,
            'donor_email' => $order->donor_email,
            'donor_phone' => $order->donor_phone,
            'source' => DonationAttributionService::trafficSourceLabel($order),
            'utm_campaign' => $order->utm_campaign ?: null,
            'utm_content' => $order->utm_content ?: null,
            'cause' => $item?->causeModel?->title ?? '—',
            'cause_title' => $causeTitleLines === [] ? '—' : implode('; ', $causeTitleLines),
            'cause_title_lines' => $causeTitleLines,
            'city' => $order->city ?: '—',
            'total_amount' => (float) $order->total_amount,
            'status' => $order->status,
            'created_date' => $order->created_at?->format('d M Y'),
            'created_time' => $order->created_at?->format('h:i A'),
            'created_at_ts' => $order->created_at?->timestamp ?? 0,
            'edit_url' => $order->isPaid()
                ? route('admin.donations.edit', $order)
                : null,
        ];
    }

    /**
     * One table row per Daily Needs product line; other donations stay one row.
     *
     * @return list<array<string, mixed>>
     */
    public static function donationTableRows(DonationOrder $order, ?string $causeTitleFilter = null): array
    {
        $item = $order->items->first();
        $lines = self::dailyNeedsParsedLines($item);
        $base = self::donationRow($order);
        $needle = trim((string) $causeTitleFilter);

        if ($lines === []) {
            if ($needle !== '' && ! self::causeTitleMatches($base['cause_title'] ?? '', $needle)) {
                return [];
            }

            return [$base];
        }

        return collect($lines)
            ->values()
            ->map(function (array $line, int $index) use ($order, $base) {
                $label = self::formatDailyNeedsCauseTitleLine($line, includeAmount: false);
                $productTitle = trim((string) ($line['title'] ?? ''));

                return [
                    ...$base,
                    'id' => $order->id.':dn:'.$index,
                    'cause_title' => $label,
                    'cause_title_lines' => [$label],
                    'cause_title_product' => $productTitle,
                    'total_amount' => (float) ($line['amount'] ?? 0),
                ];
            })
            ->when(
                $needle !== '',
                fn ($rows) => $rows->filter(
                    fn (array $row) => self::causeTitleMatches($row['cause_title_product'] ?? '', $needle)
                        || self::causeTitleMatches($row['cause_title'] ?? '', $needle)
                )
            )
            ->values()
            ->all();
    }

    public static function causeTitleMatches(string $haystack, string $needle): bool
    {
        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value));
            $value = preg_replace('/\([^)]*\)/', ' ', $value) ?? $value;
            $value = preg_replace('/\s+/', ' ', $value) ?? $value;

            return trim($value);
        };

        $haystack = $normalize($haystack);
        $needle = $normalize($needle);

        if ($haystack === '' || $needle === '') {
            return false;
        }

        return $haystack === $needle
            || str_contains($haystack, $needle)
            || str_contains($needle, $haystack);
    }

    /**
     * Package / selection title shown next to the cause name
     * (e.g. "Plant A Bili Patra Tree").
     * Daily Needs returns every cart line joined with "; ".
     */
    public static function donationItemCauseTitle(?DonationItem $item): string
    {
        $lines = self::donationItemCauseTitleLines($item);

        return $lines === [] ? '—' : implode('; ', $lines);
    }

    /**
     * @return list<string>
     */
    public static function donationItemCauseTitleLines(?DonationItem $item): array
    {
        if (! $item) {
            return [];
        }

        $parsed = self::dailyNeedsParsedLines($item);
        if ($parsed !== []) {
            return array_map(
                fn (array $line) => self::formatDailyNeedsCauseTitleLine($line),
                $parsed
            );
        }

        $item->loadMissing('package');

        $packageTitle = trim((string) ($item->package?->title ?? ''));
        if ($packageTitle !== '') {
            return [$packageTitle];
        }

        $meta = is_array($item->meta) ? $item->meta : [];
        $dailyNeedsSummary = trim((string) ($meta['daily_needs_summary'] ?? ''));
        $fallbackTitle = $dailyNeedsSummary !== '' ? $dailyNeedsSummary : trim((string) ($item->title ?? ''));

        return $fallbackTitle === '' ? [] : [$fallbackTitle];
    }

    /**
     * @return list<array{title: string, qty: int, unit: string, qty_label: string, unit_price: float, amount: float}>
     */
    public static function dailyNeedsParsedLines(?DonationItem $item): array
    {
        if (! $item) {
            return [];
        }

        $item->loadMissing('causeModel');

        $meta = is_array($item->meta) ? $item->meta : [];
        $dailyNeedsSummary = trim((string) ($meta['daily_needs_summary'] ?? ''));
        $fallbackTitle = $dailyNeedsSummary !== '' ? $dailyNeedsSummary : trim((string) ($item->title ?? ''));
        $slug = strtolower((string) ($item->causeModel?->slug ?? ($meta['cause_slug'] ?? $item->cause)));
        $normalizedCause = strtolower(str_replace(['_', ' '], '-', (string) $item->cause));

        $isDailyNeeds = $slug === Cause::SLUG_DAILY_NEEDS
            || $normalizedCause === Cause::SLUG_DAILY_NEEDS
            || $normalizedCause === 'daily-need'
            || $dailyNeedsSummary !== ''
            || AdminInertiaResources::looksLikeDailyNeedsSource($fallbackTitle);

        if (! $isDailyNeeds) {
            return [];
        }

        return AdminInertiaResources::dailyNeedsLinesFromMeta($meta, $fallbackTitle);
    }

    /**
     * @param  array{title: string, qty: int, unit: string, qty_label: string, unit_price: float, amount: float}  $line
     */
    public static function formatDailyNeedsCauseTitleLine(array $line, bool $includeAmount = true): string
    {
        $qtyLabel = trim((string) ($line['qty_label'] ?? ''));
        if ($qtyLabel === '') {
            $qty = max(1, (int) ($line['qty'] ?? 1));
            $unit = trim((string) ($line['unit'] ?? ''));
            $qtyLabel = $unit !== '' ? $qty.$unit : (string) $qty;
        }

        $title = trim((string) ($line['title'] ?? ''));
        $label = trim($qtyLabel.' '.$title);
        $amount = (float) ($line['amount'] ?? 0);

        if ($includeAmount && $amount > 0) {
            return $label.' = ₹ '.number_format($amount, 2, '.', ',');
        }

        return $label !== '' ? $label : '—';
    }

    public static function offlineDonationRow(DonationOrder $order): array
    {
        $receiptLabel = 'Not sent';

        if ($order->receipt_sent_at) {
            $receiptLabel = 'Sent';
        } elseif ($order->receipt_failed_at) {
            $receiptLabel = 'Failed';
        }

        return [
            ...self::donationRow($order),
            'receipt_number' => $order->hasReceipt() ? $order->receiptNumberFormatted() : '—',
            'receipt_email_label' => $receiptLabel,
            'receipt_preview_url' => route('admin.donations.receipt.preview', $order),
            'receipt_print_url' => route('admin.donations.receipt.print', $order),
            'receipt_resend_url' => route('admin.donations.receipt.resend', $order),
            'receipt_generate_url' => route('admin.donations.receipt.generate', $order),
            'edit_url' => route('admin.donations.edit', $order),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function offlineDonationTableRows(DonationOrder $order, ?string $causeTitleFilter = null): array
    {
        $extra = self::offlineDonationRow($order);

        return collect(self::donationTableRows($order, $causeTitleFilter))
            ->map(fn (array $row) => [
                ...$row,
                'receipt_number' => $extra['receipt_number'],
                'receipt_email_label' => $extra['receipt_email_label'],
                'receipt_preview_url' => $extra['receipt_preview_url'],
                'receipt_print_url' => $extra['receipt_print_url'],
                'receipt_resend_url' => $extra['receipt_resend_url'],
                'receipt_generate_url' => $extra['receipt_generate_url'],
                'edit_url' => $extra['edit_url'],
            ])
            ->all();
    }

    public static function paginatedDonations(
        LengthAwarePaginator $paginator,
        bool $offline = false,
        ?string $causeTitleFilter = null,
    ): array {
        $mapper = $offline
            ? fn (DonationOrder $order) => self::offlineDonationTableRows($order, $causeTitleFilter)
            : fn (DonationOrder $order) => self::donationTableRows($order, $causeTitleFilter);

        return [
            'data' => collect($paginator->items())->flatMap($mapper)->values()->all(),
            'links' => $paginator->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public static function recoveryRow(DonationOrder $order): array
    {
        $canNudgeWhatsApp = app(DonationWhatsAppPolicy::class)
            ->hasSendablePhoneNumber($order->donor_phone);
        $canNudgeSms = $canNudgeWhatsApp;
        $canNudgeEmail = app(RazorpayPaymentLinkService::class)
            ->hasSendableEmail($order->donor_email);

        $nudgeLabel = 'Ready';
        if (! $canNudgeWhatsApp && ! $canNudgeEmail) {
            $nudgeLabel = 'No contact';
        } elseif (! $canNudgeWhatsApp) {
            $nudgeLabel = 'No phone';
        } elseif ($order->payment_link_sent_at || $order->payment_link_email_sent_at || $order->payment_link_sms_sent_at) {
            $nudgeLabel = 'Sent';
        } elseif (filled($order->payment_link_url)) {
            $nudgeLabel = 'Link ready';
        }

        return [
            ...self::donationRow($order),
            'failed_at' => $order->failed_at?->format('d M Y, h:i A'),
            'payment_link_url' => $order->payment_link_url,
            'payment_link_sent_at' => $order->payment_link_sent_at?->format('d M Y, h:i A'),
            'payment_link_email_sent_at' => $order->payment_link_email_sent_at?->format('d M Y, h:i A'),
            'payment_link_sms_sent_at' => $order->payment_link_sms_sent_at?->format('d M Y, h:i A'),
            'can_nudge' => $canNudgeWhatsApp,
            'can_nudge_whatsapp' => $canNudgeWhatsApp,
            'can_nudge_email' => $canNudgeEmail,
            'can_nudge_sms' => $canNudgeSms,
            'nudge_label' => $nudgeLabel,
            'show_url' => route('admin.donations.show', $order),
        ];
    }

    public static function paginatedRecoveryOrders(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())->map(fn (DonationOrder $order) => self::recoveryRow($order))->values()->all(),
            'links' => $paginator->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
