<?php

namespace App\Services\Danamojo;

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Services\DonationAttributionService;
use App\Services\DonationPaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DanamojoDonationImporter
{
    public function __construct(
        private DanamojoClient $client,
        private DonationPaymentService $donationPaymentService,
        private DonationAttributionService $donationAttribution,
    ) {}

    /**
     * @return array{fetched: int, imported: int, updated: int, skipped: int}
     */
    public function sync(Carbon $fromDate, Carbon $toDate): array
    {
        $rows = $this->client->fetchDonations($fromDate->copy()->startOfDay(), $toDate->copy()->startOfDay());

        $stats = [
            'fetched' => count($rows),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $row) {
            $result = $this->importRow($row);

            $stats[$result]++;
        }

        return $stats;
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

        if (! $this->isVerifiedStatus((string) ($row['paymentStatus'] ?? ''))) {
            return 'skipped';
        }

        $providerOrderId = $this->providerOrderId($donationInfoId);
        $existing = DonationOrder::query()
            ->where('payment_provider', DonationOrder::PROVIDER_DANAMOJO)
            ->where('provider_order_id', $providerOrderId)
            ->first();

        return DB::transaction(function () use ($row, $donationInfoId, $providerOrderId, $existing) {
            if ($existing) {
                $this->updateExistingOrder($existing, $row);

                return 'updated';
            }

            $this->createOrder($row, $donationInfoId, $providerOrderId);

            return 'imported';
        });
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

        $order = DonationOrder::create([
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
            'utm_campaign' => $this->nullableString($row['utm_campaign'] ?? null),
            'referrer' => $this->nullableString($row['refererUrl'] ?? null),
            'landing_path' => $this->landingPath($row['refererUrl'] ?? null),
            'device_type' => $this->deviceType($row['device'] ?? null),
            'is_recurring' => (bool) ($row['recurring'] ?? false),
        ]);

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

        $order->fill([
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
            'utm_campaign' => $this->nullableString($row['utm_campaign'] ?? null) ?? $order->utm_campaign,
            'referrer' => $this->nullableString($row['refererUrl'] ?? null) ?? $order->referrer,
            'landing_path' => $this->landingPath($row['refererUrl'] ?? null) ?? $order->landing_path,
            'device_type' => $this->deviceType($row['device'] ?? null) ?? $order->device_type,
            'is_recurring' => (bool) ($row['recurring'] ?? false),
        ]);
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

        $this->donationAttribution->ensureOnPaid($order->fresh(['items.causeModel']));
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
        ], fn ($value) => $value !== null && $value !== '');
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

        return is_string($path) && $path !== '' ? $path : null;
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
}
