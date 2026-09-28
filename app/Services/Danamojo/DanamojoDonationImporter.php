<?php

namespace App\Services\Danamojo;

use App\Models\Cause;
use App\Models\DanamojoDonation;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Services\DonationAttributionService;
use App\Services\DonationPaymentService;
use App\Support\Attribution\AttributionNormalizer;
use App\Support\Attribution\AttributionParameters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DanamojoDonationImporter
{
    public function __construct(
        private DanamojoClient $client,
        private DonationPaymentService $donationPaymentService,
        private DonationAttributionService $donationAttribution,
    ) {}

    /**
     * @return array{
     *     fetched: int,
     *     imported: int,
     *     updated: int,
     *     skipped: int,
     *     skipped_pending: int,
     *     skipped_failed: int,
     *     skipped_other: int,
     *     by_status: array<string, int>
     * }
     */
    public function sync(Carbon $fromDate, Carbon $toDate): array
    {
        $rows = $this->client->fetchDonations($fromDate->copy()->startOfDay(), $toDate->copy()->startOfDay());

        $stats = [
            'fetched' => count($rows),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'skipped_pending' => 0,
            'skipped_failed' => 0,
            'skipped_other' => 0,
            'by_status' => [],
        ];

        foreach ($rows as $row) {
            $status = trim((string) ($row['paymentStatus'] ?? 'unknown'));
            $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;

            $result = $this->importRow($row);

            if ($result === 'skipped') {
                $stats['skipped']++;
                $normalized = mb_strtolower($status);

                if ($normalized === 'pending') {
                    $stats['skipped_pending']++;
                } elseif ($normalized === 'failed' || $normalized === 'refunded') {
                    $stats['skipped_failed']++;
                } else {
                    $stats['skipped_other']++;
                }

                continue;
            }

            $stats[$result]++;
        }

        Log::info('danamojo.sync.completed', [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Fetch a lookback window and import a single donationInfoId when present.
     *
     * @return 'imported'|'updated'|'skipped'|'not_found'
     */
    public function importByDonationInfoId(int $donationInfoId, ?Carbon $fromDate = null, ?Carbon $toDate = null): string
    {
        if ($donationInfoId <= 0) {
            return 'skipped';
        }

        $to = $toDate?->copy() ?? now();
        $from = $fromDate?->copy()
            ?? $to->copy()->subDays(max(0, (int) config('danamojo.lookback_days', 14)));

        $rows = $this->client->fetchDonations($from->copy()->startOfDay(), $to->copy()->startOfDay());

        foreach ($rows as $row) {
            if ((int) ($row['donationInfoId'] ?? 0) !== $donationInfoId) {
                continue;
            }

            return $this->importRow($row);
        }

        $this->markRetry($donationInfoId, 'not_found');

        return 'not_found';
    }

    /**
     * Save a widget callback immediately, before Danamojo's API lists the payment.
     *
     * @param  array<string, mixed>  $input
     */
    public function recordNotify(int $donationInfoId, array $input): DanamojoDonation
    {
        $record = DanamojoDonation::query()->firstOrNew([
            'donation_info_id' => $donationInfoId,
        ]);

        $tracking = $this->trackingFromNotify($input);
        foreach ($tracking as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (blank($record->getAttribute($key))) {
                $record->setAttribute($key, $value);
            }
        }

        if ($record->sync_state !== DanamojoDonation::STATE_IMPORTED) {
            $record->sync_state = $record->payment_status
                ? $this->syncStateForStatus((string) $record->payment_status, $record)
                : DanamojoDonation::STATE_NOTIFIED;
        }

        $this->applyPartner($record);
        $record->notified_at = $record->notified_at ?? now();
        $record->notify_payload = $input;
        $record->next_retry_at = $record->sync_state === DanamojoDonation::STATE_IMPORTED
            ? null
            : now();
        $record->save();

        return $record;
    }

    /**
     * Re-apply attribution from stored referrer / item meta for existing Danamojo orders.
     *
     * @return array{scanned: int, updated: int}
     */
    public function backfillAttribution(?int $limit = null): array
    {
        $query = DonationOrder::query()
            ->where('payment_provider', DonationOrder::PROVIDER_DANAMOJO)
            ->with('items')
            ->orderByDesc('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $scanned = 0;
        $updated = 0;

        foreach ($query->cursor() as $order) {
            $scanned++;
            $meta = is_array($order->items->first()?->meta) ? $order->items->first()->meta : [];
            $danamojo = is_array($meta['danamojo'] ?? null) ? $meta['danamojo'] : [];

            $row = [
                'refererUrl' => $order->referrer,
                'utm_campaign' => $danamojo['utm_campaign'] ?? $order->utm_campaign,
                'device' => $order->device_type,
            ];

            $attribution = $this->attributionFromRow($row);
            $before = $order->only(array_keys($attribution));
            $merged = $this->mergeAttribution($order, $attribution);

            if ($merged === []) {
                continue;
            }

            $order->fill($merged)->save();
            DonationAttributionService::normalizeOrderAttributes($order->fresh());
            DonationAttributionService::resolveIdentifierColumns($order->fresh());

            $after = $order->fresh()->only(array_keys($attribution));
            if ($before !== $after) {
                $updated++;
            }
        }

        return ['scanned' => $scanned, 'updated' => $updated];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'imported'|'updated'|'skipped'
     */
    public function importRow(array $row): string
    {
        $donationInfoId = (int) ($row['donationInfoId'] ?? 0);

        if ($donationInfoId <= 0) {
            return 'skipped';
        }

        $tracked = $this->upsertApiRow($row);
        $row = $this->mergeTrackedAttribution($row, $tracked);

        if (! $this->isVerifiedStatus((string) ($row['paymentStatus'] ?? ''))) {
            return 'skipped';
        }

        $providerOrderId = $this->providerOrderId($donationInfoId);
        $existing = DonationOrder::query()
            ->where('payment_provider', DonationOrder::PROVIDER_DANAMOJO)
            ->where('provider_order_id', $providerOrderId)
            ->first();

        $result = DB::transaction(function () use ($row, $donationInfoId, $providerOrderId, $existing) {
            if ($existing) {
                $this->updateExistingOrder($existing, $row);

                return 'updated';
            }

            $this->createOrder($row, $donationInfoId, $providerOrderId);

            return 'imported';
        });

        $order = DonationOrder::query()
            ->where('payment_provider', DonationOrder::PROVIDER_DANAMOJO)
            ->where('provider_order_id', $providerOrderId)
            ->first();

        if ($order) {
            $tracked->forceFill([
                'donation_order_id' => $order->id,
                'partner_user_id' => $tracked->partner_user_id ?: $order->partner_user_id,
                'partner_code' => $tracked->partner_code ?: $order->partner_code,
                'sync_state' => DanamojoDonation::STATE_IMPORTED,
                'imported_at' => $tracked->imported_at ?? now(),
                'next_retry_at' => null,
                'last_error' => null,
            ])->save();
        }

        return $result;
    }

    public static function providerOrderId(int $donationInfoId): string
    {
        return 'danamojo-'.$donationInfoId;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createOrder(array $row, int $donationInfoId, string $providerOrderId): DonationOrder
    {
        $snapshot = $this->donorSnapshot($row);
        $donor = ($snapshot['donor_email'] !== '' || $snapshot['donor_phone'] !== '')
            ? Donor::resolveFromDonationSnapshot($snapshot)
            : null;

        $amountInr = $this->amountInr($row);
        $paidAt = $this->donationDate($row);
        $details = $this->firstDonationDetail($row);
        $cause = $this->resolveCause($details);
        $productName = trim((string) ($details['donationProductName'] ?? 'Danamojo donation'));
        $attribution = $this->attributionFromRow($row);

        $order = DonationOrder::create(array_merge([
            'payment_provider' => DonationOrder::PROVIDER_DANAMOJO,
            'source_channel' => DonationAttributionService::CHANNEL_DANAMOJO,
            'provider_order_id' => $providerOrderId,
            'provider_payment_id' => (string) ($details['receiptNumber'] ?? $donationInfoId),
            'donor_id' => $donor?->id,
            'donor_name' => $snapshot['donor_name'],
            'donor_email' => $snapshot['donor_email'] ?: null,
            'donor_phone' => $snapshot['donor_phone'] ?: null,
            'pan_number' => $snapshot['pan_number'],
            'address' => $snapshot['address'],
            'pincode' => $snapshot['pincode'],
            'city' => $snapshot['city'],
            'state' => $snapshot['state'],
            'country' => $snapshot['country'],
            'donor_country_code' => $snapshot['donor_country_code'],
            'consent_indian_citizen' => $snapshot['consent_indian_citizen'],
            'currency' => 'INR',
            'total_amount' => $amountInr,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => $paidAt,
            'device_type' => $this->deviceType($row['device'] ?? null),
            'is_recurring' => (bool) ($row['recurring'] ?? false),
        ], $attribution));

        $order->created_at = $paidAt;
        $order->saveQuietly();

        DonationItem::query()->create([
            'donation_order_id' => $order->id,
            'cause_id' => $cause?->id,
            'cause' => $cause?->slug ?? Str::slug($productName) ?: 'danamojo',
            'title' => $cause?->title ?? $productName,
            'quantity' => max(1, (int) ($details['donationProductQty'] ?? 1)),
            'unit_amount' => $amountInr,
            'amount' => $amountInr,
            'meta' => array_filter([
                'cause_title' => $cause?->title,
                'cause_slug' => $cause?->slug,
                'danamojo' => $this->danamojoMeta($row, $details),
            ]),
        ]);

        $this->applyNormalizedAttribution($order->fresh(['items.causeModel']));
        $this->finalizeNewImport($order->fresh(['items.causeModel']));

        return $order;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function updateExistingOrder(DonationOrder $order, array $row): void
    {
        $snapshot = $this->donorSnapshot($row);
        $amountInr = $this->amountInr($row);
        $paidAt = $this->donationDate($row);
        $details = $this->firstDonationDetail($row);
        $attribution = $this->mergeAttribution($order, $this->attributionFromRow($row));

        $order->fill(array_merge([
            'donor_name' => $snapshot['donor_name'],
            'donor_email' => $snapshot['donor_email'] ?: $order->donor_email,
            'donor_phone' => $snapshot['donor_phone'] ?: $order->donor_phone,
            'pan_number' => $snapshot['pan_number'] ?: $order->pan_number,
            'address' => $snapshot['address'] ?: $order->address,
            'pincode' => $snapshot['pincode'] ?: $order->pincode,
            'city' => $snapshot['city'] ?: $order->city,
            'state' => $snapshot['state'] ?: $order->state,
            'country' => $snapshot['country'] ?: $order->country,
            'donor_country_code' => $snapshot['donor_country_code'] ?: $order->donor_country_code,
            'total_amount' => $amountInr,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => $order->paid_at ?? $paidAt,
            'device_type' => $this->deviceType($row['device'] ?? null) ?? $order->device_type,
            'is_recurring' => (bool) ($row['recurring'] ?? false),
        ], $attribution));
        $order->save();

        $item = $order->items()->first();
        $cause = $this->resolveCause($details);
        $productName = trim((string) ($details['donationProductName'] ?? $item?->title ?? 'Danamojo donation'));
        $meta = is_array($item?->meta) ? $item->meta : [];
        $meta['danamojo'] = $this->danamojoMeta($row, $details);

        if ($cause) {
            $meta['cause_title'] = $cause->title;
            $meta['cause_slug'] = $cause->slug;
        }

        if ($item) {
            $item->update([
                'cause_id' => $cause?->id ?? $item->cause_id,
                'cause' => $cause?->slug ?? $item->cause,
                'title' => $cause?->title ?? $productName,
                'unit_amount' => $amountInr,
                'amount' => $amountInr,
                'meta' => array_filter($meta),
            ]);
        }

        $fresh = $order->fresh(['items.causeModel']);
        $this->applyNormalizedAttribution($fresh);
        $this->donationAttribution->ensureOnPaid($fresh->fresh(['items.causeModel']));
    }

    private function applyNormalizedAttribution(DonationOrder $order): void
    {
        DonationAttributionService::normalizeOrderAttributes($order);
        DonationAttributionService::resolveIdentifierColumns($order->fresh());
    }

    /**
     * Prefer URL query UTMs; fall back to Danamojo's root utm_campaign.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, int|string>
     */
    private function attributionFromRow(array $row): array
    {
        $referrer = $this->nullableString($row['refererUrl'] ?? null);
        $query = $this->queryParamsFromUrl($referrer);
        $tracked = is_array($row['_tracked'] ?? null) ? $row['_tracked'] : [];

        $utmSource = $this->nullableLimited($query['utm_source'] ?? $tracked['utm_source'] ?? null, 120);
        $utmMedium = $this->nullableLimited($query['utm_medium'] ?? $tracked['utm_medium'] ?? null, 120);
        $utmCampaign = $this->nullableLimited($query['utm_campaign'] ?? $tracked['utm_campaign'] ?? null, 120)
            ?? $this->nullableLimited($row['utm_campaign'] ?? null, 120);
        $utmContent = $this->nullableLimited($query['utm_content'] ?? $tracked['utm_content'] ?? null, 120);
        $utmTerm = $this->nullableLimited($query['utm_term'] ?? $tracked['utm_term'] ?? null, 120);

        $payload = array_filter([
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $utmContent,
            'utm_term' => $utmTerm,
            'referrer' => $referrer ? mb_substr($referrer, 0, 512) : null,
            'landing_path' => $this->landingPath($referrer),
            'meta_campaign_id' => $this->nullableLimited(
                $query['utm_id'] ?? $tracked['utm_id'] ?? $query['campaign_id'] ?? $query['fb_campaign_id'] ?? null,
                40
            ),
            'meta_adset_id' => $this->nullableLimited(
                $query['adset_id'] ?? $query['fb_adset_id'] ?? null,
                40
            ),
            'meta_ad_id' => $this->nullableLimited(
                $query['aid'] ?? $tracked['aid'] ?? $query['ad_id'] ?? $query['fb_ad_id'] ?? null,
                40
            ),
        ], fn ($value) => $value !== null && $value !== '');

        $partner = AttributionParameters::resolvePartner(
            AttributionParameters::withSidAlias([
                'sid' => $query['sid'] ?? $tracked['sid'] ?? null,
                'utm_sid' => $query['utm_sid'] ?? null,
                'pid' => $query['pid'] ?? null,
                'utm_source' => $utmSource,
                'utm_content' => $utmContent,
            ])
        );
        if (($partner['partner_code'] ?? null) !== null) {
            $payload['partner_code'] = $partner['partner_code'];
        }
        if (($partner['partner_user_id'] ?? null) !== null) {
            $payload['partner_user_id'] = $partner['partner_user_id'];
        }

        $normalized = AttributionNormalizer::normalizedPayload([
            'utm_source' => $payload['utm_source'] ?? null,
            'utm_medium' => $payload['utm_medium'] ?? null,
            'utm_campaign' => $payload['utm_campaign'] ?? null,
            'utm_content' => $payload['utm_content'] ?? null,
            'utm_term' => $payload['utm_term'] ?? null,
            'referrer' => $payload['referrer'] ?? null,
            'landing_path' => $payload['landing_path'] ?? null,
        ]);

        return array_merge($payload, array_filter($normalized, fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * Fill empty attribution columns; keep existing non-empty values.
     *
     * @param  array<string, string>  $incoming
     * @return array<string, string>
     */
    private function mergeAttribution(DonationOrder $order, array $incoming): array
    {
        $merged = [];

        foreach ($incoming as $key => $value) {
            $current = $order->getAttribute($key);
            if ($current === null || $current === '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @return array<string, string>
     */
    private function queryParamsFromUrl(?string $url): array
    {
        if ($url === null || $url === '') {
            return [];
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return [];
        }

        $params = [];
        parse_str($query, $params);

        $out = [];
        foreach ($params as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                $out[$key] = AttributionParameters::decodeQueryValue($trimmed);
            }
        }

        return $out;
    }

    private function finalizeNewImport(DonationOrder $order): void
    {
        $sendEmail = (bool) config('danamojo.send_receipt_email_on_import', false);
        $sendWhatsApp = (bool) config('danamojo.send_whatsapp_on_import', false);
        $queueSheet = (bool) config('danamojo.queue_sheet_on_import', true);

        if (! $queueSheet && ! $order->sheet_logged_at) {
            $order->update(['sheet_logged_at' => $order->paid_at ?? now()]);
            $order->refresh();
        }

        $this->donationPaymentService->generateManualReceipt(
            $order,
            $sendEmail,
            $sendWhatsApp,
            $sendWhatsApp,
        );

        $order->refresh();

        // Danamojo already emails its own receipt — mark ours sent unless we queued one.
        if (! $sendEmail && ! $order->receipt_sent_at) {
            $order->update(['receipt_sent_at' => $order->paid_at ?? now()]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     donor_name: string,
     *     donor_email: string,
     *     donor_phone: string,
     *     pan_number: ?string,
     *     address: ?string,
     *     pincode: ?string,
     *     city: ?string,
     *     state: ?string,
     *     country: string,
     *     donor_country_code: string,
     *     consent_indian_citizen: bool,
     *     date_of_birth: null
     * }
     */
    private function donorSnapshot(array $row): array
    {
        $country = trim((string) ($row['country'] ?? $row['nationality'] ?? 'INDIA'));
        $countryCode = $this->countryCode($country, (string) ($row['mobile'] ?? ''));
        $idProof = trim((string) ($row['idProof'] ?? ''));
        $idValue = trim((string) ($row['id'] ?? ''));

        $pan = null;
        if ($idValue !== '' && (str_contains(mb_strtolower($idProof), 'pan') || preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/i', $idValue))) {
            $pan = mb_strtoupper($idValue);
        }

        return [
            'donor_name' => trim((string) ($row['fullName'] ?? '')) ?: 'Unknown Donor',
            'donor_email' => mb_strtolower(trim((string) ($row['email'] ?? ''))),
            'donor_phone' => trim((string) ($row['mobile'] ?? '')),
            'pan_number' => $pan,
            'address' => $this->nullableString($row['address'] ?? null),
            'pincode' => $this->nullableString($row['pincode'] ?? null),
            'city' => $this->nullableString($row['city'] ?? null),
            'state' => $this->nullableString($row['state'] ?? null),
            'country' => $country !== '' ? mb_strtoupper($country) : 'INDIA',
            'donor_country_code' => $countryCode,
            'consent_indian_citizen' => $countryCode === 'IN' && ! (bool) ($row['international'] ?? false),
            'date_of_birth' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function amountInr(array $row): float
    {
        // International rows: totalDonationAmt is INR 80G amount; local is foreign currency.
        $inr = (float) ($row['totalDonationAmt'] ?? 0);

        if ($inr > 0) {
            return round($inr, 2);
        }

        return round((float) ($row['totalDonationAmtLocal'] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function donationDate(array $row): Carbon
    {
        $raw = $row['donationDate'] ?? $row['firstRecognizedDate'] ?? null;

        return $raw ? Carbon::parse((string) $raw) : now();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function firstDonationDetail(array $row): array
    {
        $details = $row['donation_details'] ?? [];

        if (is_array($details) && isset($details[0]) && is_array($details[0])) {
            return $details[0];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function resolveCause(array $details): ?Cause
    {
        $productName = trim((string) ($details['donationProductName'] ?? ''));

        if ($productName === '') {
            return $this->defaultCause();
        }

        /** @var array<string, string> $map */
        $map = config('danamojo.product_cause_map', []);

        foreach ($map as $name => $slug) {
            if (strcasecmp((string) $name, $productName) === 0 && filled($slug)) {
                $mapped = Cause::query()->where('slug', $slug)->first();

                if ($mapped) {
                    return $mapped;
                }
            }
        }

        $exact = Cause::query()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($productName)])
            ->first();

        if ($exact) {
            return $exact;
        }

        $slugGuess = Str::slug($productName);
        $bySlug = Cause::query()->where('slug', $slugGuess)->first();

        if ($bySlug) {
            return $bySlug;
        }

        $fuzzy = Cause::query()
            ->where('title', 'like', '%'.$productName.'%')
            ->orWhere('slug', 'like', '%'.$slugGuess.'%')
            ->first();

        return $fuzzy ?: $this->defaultCause();
    }

    private function defaultCause(): ?Cause
    {
        $slug = config('danamojo.default_cause_slug');

        if (! filled($slug)) {
            return null;
        }

        return Cause::query()->where('slug', $slug)->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function danamojoMeta(array $row, array $details): array
    {
        return array_filter([
            'donation_info_id' => (int) ($row['donationInfoId'] ?? 0),
            'receipt_number' => $details['receiptNumber'] ?? null,
            'receipt_link' => $row['receiptLink'] ?? null,
            'payment_option' => $row['paymentOption'] ?? null,
            'payment_status' => $row['paymentStatus'] ?? null,
            'currency' => $row['currency'] ?? null,
            'amount_local' => $row['totalDonationAmtLocal'] ?? null,
            'amount_inr' => $row['totalDonationAmt'] ?? null,
            'fcra' => (bool) ($row['fcra'] ?? false),
            'international' => (bool) ($row['international'] ?? false),
            'product_name' => $details['donationProductName'] ?? null,
            'sub_id' => $row['subId'] ?? null,
            'utm_campaign' => $row['utm_campaign'] ?? null,
            'referer_url' => $row['refererUrl'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertApiRow(array $row): DanamojoDonation
    {
        $donationInfoId = (int) ($row['donationInfoId'] ?? 0);
        $record = DanamojoDonation::query()->firstOrNew([
            'donation_info_id' => $donationInfoId,
        ]);

        $details = $this->firstDonationDetail($row);
        $status = trim((string) ($row['paymentStatus'] ?? ''));
        $snapshot = $this->donorSnapshot($row);

        $incoming = array_filter([
            'payment_status' => $status !== '' ? $status : null,
            'donor_name' => $snapshot['donor_name'] !== '' ? $snapshot['donor_name'] : null,
            'donor_email' => $snapshot['donor_email'] !== '' ? $snapshot['donor_email'] : null,
            'donor_phone' => $snapshot['donor_phone'] !== '' ? $snapshot['donor_phone'] : null,
            'nationality' => $this->nullableString($row['nationality'] ?? null),
            'country' => $snapshot['country'] !== '' ? $snapshot['country'] : null,
            'currency' => $this->nullableString($row['currency'] ?? null),
            'amount_local' => is_numeric($row['totalDonationAmtLocal'] ?? null) ? round((float) $row['totalDonationAmtLocal'], 2) : null,
            'amount_inr' => $this->amountInr($row) > 0 ? $this->amountInr($row) : null,
            'payment_option' => $this->nullableString($row['paymentOption'] ?? null),
            'product_name' => $this->nullableString($details['donationProductName'] ?? null),
            'receipt_number' => $this->nullableString($details['receiptNumber'] ?? null),
            'receipt_link' => $this->nullableString($row['receiptLink'] ?? null),
            'referer_url' => $this->nullableString($row['refererUrl'] ?? null),
            'device' => $this->nullableString($row['device'] ?? null),
            'recurring' => (bool) ($row['recurring'] ?? false),
            'fcra' => array_key_exists('fcra', $row) ? (bool) $row['fcra'] : null,
            'international' => array_key_exists('international', $row) ? (bool) $row['international'] : null,
            'donated_at' => $this->donationDate($row),
        ], fn ($value) => $value !== null && $value !== '');

        foreach ($incoming as $key => $value) {
            $record->setAttribute($key, $value);
        }

        $query = $this->queryParamsFromUrl($this->nullableString($row['refererUrl'] ?? null));
        $this->fillBlankTracking($record, [
            'sid' => $query['sid'] ?? null,
            'utm_source' => $query['utm_source'] ?? null,
            'utm_medium' => $query['utm_medium'] ?? null,
            'utm_campaign' => $query['utm_campaign'] ?? ($row['utm_campaign'] ?? null),
            'utm_content' => $query['utm_content'] ?? null,
            'utm_term' => $query['utm_term'] ?? null,
            'utm_id' => $query['utm_id'] ?? null,
            'aid' => $query['aid'] ?? null,
        ]);
        $this->applyPartner($record);

        if ($record->sync_state !== DanamojoDonation::STATE_IMPORTED) {
            $record->sync_state = $this->syncStateForStatus($status, $record);
            $record->next_retry_at = $record->sync_state === DanamojoDonation::STATE_PENDING
                ? now()->addMinutes(2)
                : null;
        }

        $record->last_synced_at = now();
        $record->last_error = null;
        $record->raw_payload = $row;
        $record->save();

        return $record;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mergeTrackedAttribution(array $row, DanamojoDonation $tracked): array
    {
        $referrer = trim((string) ($row['refererUrl'] ?? ''));
        $landing = trim((string) ($tracked->landing_url ?? ''));

        if ($landing !== '' && ($referrer === '' || ! str_contains($referrer, '?'))) {
            $row['refererUrl'] = $landing;
        }

        $row['_tracked'] = array_filter([
            'sid' => $tracked->sid,
            'utm_source' => $tracked->utm_source,
            'utm_medium' => $tracked->utm_medium,
            'utm_campaign' => $tracked->utm_campaign,
            'utm_content' => $tracked->utm_content,
            'utm_term' => $tracked->utm_term,
            'utm_id' => $tracked->utm_id,
            'aid' => $tracked->aid,
        ], fn ($value) => is_string($value) && $value !== '');

        return $row;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function trackingFromNotify(array $input): array
    {
        $landing = $this->nullableString($input['landing_url'] ?? null);
        $query = $this->queryParamsFromUrl($landing);

        return [
            'dm_status' => $this->nullableLimited($input['dmStatus'] ?? null, 40),
            'landing_url' => $landing,
            'referer_url' => $this->nullableString($input['referrer'] ?? null),
            'sid' => $this->nullableLimited($input['sid'] ?? $query['sid'] ?? null, 40),
            'utm_source' => $this->nullableLimited($input['utm_source'] ?? $query['utm_source'] ?? null, 120),
            'utm_medium' => $this->nullableLimited($input['utm_medium'] ?? $query['utm_medium'] ?? null, 120),
            'utm_campaign' => $this->nullableLimited($input['utm_campaign'] ?? $query['utm_campaign'] ?? null, 120),
            'utm_content' => $this->nullableLimited($input['utm_content'] ?? $query['utm_content'] ?? null, 120),
            'utm_term' => $this->nullableLimited($input['utm_term'] ?? $query['utm_term'] ?? null, 120),
            'utm_id' => $this->nullableLimited($input['utm_id'] ?? $query['utm_id'] ?? null, 40),
            'aid' => $this->nullableLimited($input['aid'] ?? $query['aid'] ?? null, 40),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function fillBlankTracking(DanamojoDonation $record, array $values): void
    {
        foreach ($values as $key => $value) {
            $clean = $this->nullableString(is_scalar($value) ? (string) $value : null);
            if ($clean !== null && blank($record->getAttribute($key))) {
                $record->setAttribute($key, $clean);
            }
        }
    }

    private function applyPartner(DanamojoDonation $record): void
    {
        $partner = AttributionParameters::resolvePartner(
            AttributionParameters::withSidAlias([
                'sid' => $record->sid,
                'utm_source' => $record->utm_source,
                'utm_content' => $record->utm_content,
            ])
        );

        if (blank($record->partner_code) && filled($partner['partner_code'] ?? null)) {
            $record->partner_code = $partner['partner_code'];
        }

        if ($record->partner_user_id === null && filled($partner['partner_user_id'] ?? null)) {
            $record->partner_user_id = $partner['partner_user_id'];
        }
    }

    private function syncStateForStatus(string $status, DanamojoDonation $record): string
    {
        if ($record->donation_order_id) {
            return DanamojoDonation::STATE_IMPORTED;
        }

        $normalized = mb_strtolower(trim($status));

        if ($normalized === '') {
            return DanamojoDonation::STATE_NOTIFIED;
        }

        if (in_array($normalized, ['failed', 'refunded'], true)) {
            return DanamojoDonation::STATE_FAILED;
        }

        if ($this->isVerifiedStatus($status)) {
            return DanamojoDonation::STATE_PENDING;
        }

        return DanamojoDonation::STATE_PENDING;
    }

    private function markRetry(int $donationInfoId, string $error): void
    {
        $record = DanamojoDonation::query()->where('donation_info_id', $donationInfoId)->first();

        if ($record === null || $record->sync_state === DanamojoDonation::STATE_IMPORTED) {
            return;
        }

        $record->forceFill([
            'retry_count' => $record->retry_count + 1,
            'last_error' => Str::limit($error, 255, ''),
            'next_retry_at' => now()->addMinutes(2),
        ])->save();
    }

    private function isVerifiedStatus(string $status): bool
    {
        $allowed = config('danamojo.verified_statuses', ['Verified']);

        foreach ($allowed as $value) {
            if (strcasecmp((string) $value, $status) === 0) {
                return true;
            }
        }

        return false;
    }

    private function countryCode(string $country, string $mobile): string
    {
        $normalized = mb_strtoupper(trim($country));
        $map = [
            'INDIA' => 'IN',
            'IN' => 'IN',
            'UNITED KINGDOM' => 'GB',
            'UK' => 'GB',
            'GREAT BRITAIN' => 'GB',
            'ENGLAND' => 'GB',
            'UNITED STATES' => 'US',
            'USA' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'CANADA' => 'CA',
            'UNITED ARAB EMIRATES' => 'AE',
            'UAE' => 'AE',
            'AUSTRALIA' => 'AU',
            'SINGAPORE' => 'SG',
            'NEW ZEALAND' => 'NZ',
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        if (str_starts_with(trim($mobile), '+44')) {
            return 'GB';
        }

        if (str_starts_with(trim($mobile), '+1')) {
            return 'US';
        }

        if (str_starts_with(trim($mobile), '+91')) {
            return 'IN';
        }

        return strlen($normalized) === 2 ? $normalized : 'IN';
    }

    private function landingPath(mixed $refererUrl): ?string
    {
        $url = $this->nullableString($refererUrl);

        if ($url === null) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? mb_substr($path, 0, 255) : null;
    }

    private function deviceType(mixed $device): ?string
    {
        $value = $this->nullableString($device);

        return $value ? mb_strtolower($value) : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function nullableLimited(mixed $value, int $max): ?string
    {
        $string = $this->nullableString($value);

        if ($string === null) {
            return null;
        }

        return mb_substr($string, 0, $max);
    }
}
