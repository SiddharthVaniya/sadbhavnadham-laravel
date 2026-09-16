<?php

namespace App\Services;

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Donor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyDonationImportService
{
    /**
     * @var array<string, string>
     */
    public const CAUSE_SLUG_MAP = [
        'vruddhashram' => 'old-age-home',
        'tree' => 'tree-plantation',
        'animal_hospital' => 'animal-hospital',
        'badad' => 'bull-shelter',
        'balad' => 'bull-shelter',
        'swan' => 'dog-shelter',
    ];

    /**
     * @return array{
     *     total: int,
     *     would_import: int,
     *     imported: int,
     *     skipped_existing: int,
     *     backfilled: int,
     *     by_status: array<string, int>,
     *     by_cause: array<string, int>,
     *     unmapped_causes: array<string, int>,
     *     with_address: int,
     *     errors: list<string>
     * }
     */
    public function importFromSqlFile(string $path, bool $dryRun = true, ?int $limit = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("SQL file not found: {$path}");
        }

        $rows = $this->parseDonationRows(file_get_contents($path) ?: '');

        if ($limit !== null) {
            $rows = array_slice($rows, 0, max(0, $limit));
        }

        $stats = [
            'total' => count($rows),
            'would_import' => 0,
            'imported' => 0,
            'skipped_existing' => 0,
            'backfilled' => 0,
            'by_status' => [],
            'by_cause' => [],
            'unmapped_causes' => [],
            'with_address' => 0,
            'errors' => [],
        ];

        $causesBySlug = null;

        if (! $dryRun) {
            $causesBySlug = Cause::query()->get()->keyBy('slug');
        }

        foreach ($rows as $row) {
            try {
                $mappedStatus = $this->mapStatus((string) $row['status']);
                $stats['by_status'][$mappedStatus] = ($stats['by_status'][$mappedStatus] ?? 0) + 1;

                $causeKey = strtolower(trim((string) ($row['donate_for'] ?? '')));
                $stats['by_cause'][$causeKey !== '' ? $causeKey : '(empty)'] =
                    ($stats['by_cause'][$causeKey !== '' ? $causeKey : '(empty)'] ?? 0) + 1;

                if ($causeKey !== '' && ! array_key_exists($causeKey, self::CAUSE_SLUG_MAP)) {
                    $stats['unmapped_causes'][$causeKey] = ($stats['unmapped_causes'][$causeKey] ?? 0) + 1;
                }

                $address = $this->nullableString($row['address'] ?? null);
                if ($address !== null) {
                    $stats['with_address']++;
                }

                $providerPaymentId = $this->nullableString($row['payment_id'] ?? null);
                $providerOrderId = $this->nullableString($row['razorpay_order_id'] ?? null)
                    ?: 'legacy-'.$row['id'];

                $existing = $dryRun
                    ? null
                    : $this->findExistingOrder($providerPaymentId, $providerOrderId, (int) $row['id']);

                if ($existing) {
                    if ($this->backfillMissingFields($existing, $row)) {
                        $stats['backfilled']++;
                    } else {
                        $stats['skipped_existing']++;
                    }

                    continue;
                }

                $stats['would_import']++;

                if ($dryRun) {
                    continue;
                }

                $this->importRow($row, $mappedStatus, $providerPaymentId, $providerOrderId, $causesBySlug);
                $stats['imported']++;
            } catch (\Throwable $e) {
                $stats['errors'][] = 'legacy id '.$row['id'].': '.$e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  \Illuminate\Support\Collection<string, Cause>  $causesBySlug
     */
    private function importRow(
        array $row,
        string $mappedStatus,
        ?string $providerPaymentId,
        string $providerOrderId,
        $causesBySlug,
    ): void {
        DB::transaction(function () use ($row, $mappedStatus, $providerPaymentId, $providerOrderId, $causesBySlug): void {
            $createdAt = $this->parseTimestamp($row['created_at'] ?? null) ?? now();
            $updatedAt = $this->parseTimestamp($row['updated_at'] ?? null) ?? $createdAt;

            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
            $phone = $this->normalizePhone($row['contact'] ?? null, $email, (int) $row['id']);
            $name = trim((string) ($row['donor_name'] ?? ''));
            if ($name === '') {
                $name = 'Unknown Donor';
            }

            $pan = $this->normalizePan($row['pan_number'] ?? null);
            $address = $this->nullableString($row['address'] ?? null);
            $amount = round((float) $row['amount'], 2);

            $snapshot = [
                'donor_name' => $name,
                'donor_email' => $email,
                'donor_phone' => $phone,
                'pan_number' => $pan,
                'address' => $address,
                'country' => 'INDIA',
                'donor_country_code' => 'IN',
                'consent_indian_citizen' => false,
            ];

            $donor = Donor::resolveFromDonationSnapshot($snapshot);

            if ($mappedStatus === DonationOrder::STATUS_PAID) {
                $donor->forceFill(['last_donated_at' => $createdAt])->save();
            }

            $causeSlug = $this->mapCauseSlug((string) ($row['donate_for'] ?? ''));
            $cause = $causeSlug ? $causesBySlug->get($causeSlug) : null;

            $orderPayload = [
                'order_uuid' => (string) Str::uuid(),
                'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
                'source_channel' => DonationAttributionService::CHANNEL_IMPORT,
                'provider_order_id' => $providerOrderId,
                'provider_payment_id' => $providerPaymentId,
                'donor_id' => $donor->id,
                'donor_name' => $name,
                'donor_email' => $email,
                'donor_phone' => $phone,
                'pan_number' => $pan,
                'address' => $address,
                'country' => 'INDIA',
                'donor_country_code' => 'IN',
                'currency' => 'INR',
                'total_amount' => $amount,
                'status' => $mappedStatus,
                'receipt_number' => $this->nullableString($row['receipt_no'] ?? null),
                'payment_link_id' => $this->nullableString($row['payment_link_id'] ?? null),
                'payment_link_url' => $this->nullableString($row['payment_link_url'] ?? null),
                'paid_at' => $mappedStatus === DonationOrder::STATUS_PAID ? $createdAt : null,
                'failed_at' => $mappedStatus === DonationOrder::STATUS_FAILED ? $updatedAt : null,
                // Prevent reconcile/backfill from emailing / WhatsApp / sheets for history.
                'receipt_sent_at' => $mappedStatus === DonationOrder::STATUS_PAID ? $createdAt : null,
                'sheet_logged_at' => $mappedStatus === DonationOrder::STATUS_PAID ? $createdAt : null,
                'whatsapp_sent_at' => $mappedStatus === DonationOrder::STATUS_PAID ? $createdAt : null,
                'certificate_whatsapp_sent_at' => $mappedStatus === DonationOrder::STATUS_PAID ? $createdAt : null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];

            $order = new DonationOrder($orderPayload);
            $order->timestamps = false;
            $order->save();

            DonationItem::query()->create([
                'donation_order_id' => $order->id,
                'cause_id' => $cause?->id,
                'cause' => $cause?->slug ?? $causeSlug ?? '',
                'title' => $cause?->title ?? ($causeSlug ? Str::title(str_replace('-', ' ', $causeSlug)) : 'Legacy donation'),
                'quantity' => 1,
                'unit_amount' => $amount,
                'amount' => $amount,
                'meta' => array_filter([
                    'legacy_donation_id' => (int) $row['id'],
                    'legacy_donate_for' => $row['donate_for'] ?? null,
                    'legacy_status' => $row['status'] ?? null,
                    'cause_title' => $cause?->title,
                    'cause_slug' => $cause?->slug,
                ]),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function backfillMissingFields(DonationOrder $order, array $row): bool
    {
        $address = $this->nullableString($row['address'] ?? null);
        $pan = $this->normalizePan($row['pan_number'] ?? null);
        $changed = false;

        if ($address !== null && blank($order->address)) {
            $order->address = $address;
            $changed = true;
        }

        if ($pan !== null && blank($order->pan_number)) {
            $order->pan_number = $pan;
            $changed = true;
        }

        if (! $changed) {
            return false;
        }

        $order->save();

        if ($order->donor_id) {
            $donor = Donor::query()->find($order->donor_id);

            if ($donor) {
                if ($address !== null && blank($donor->address)) {
                    $donor->address = $address;
                }

                if ($pan !== null && blank($donor->pan_number)) {
                    $donor->pan_number = $pan;
                }

                $donor->save();
            }
        }

        return true;
    }

    private function findExistingOrder(?string $providerPaymentId, string $providerOrderId, int $legacyId): ?DonationOrder
    {
        return DonationOrder::query()
            ->where(function ($inner) use ($providerPaymentId, $providerOrderId, $legacyId): void {
                $inner->where('provider_order_id', $providerOrderId)
                    ->orWhere('provider_order_id', 'legacy-'.$legacyId);

                if ($providerPaymentId) {
                    $inner->orWhere('provider_payment_id', $providerPaymentId);
                }
            })
            ->first();
    }

    private function alreadyImported(?string $providerPaymentId, string $providerOrderId, int $legacyId): bool
    {
        return $this->findExistingOrder($providerPaymentId, $providerOrderId, $legacyId) !== null;
    }

    public function mapStatus(string $legacyStatus): string
    {
        $status = strtolower(trim($legacyStatus));

        return match (true) {
            $status === 'captured' => DonationOrder::STATUS_PAID,
            str_starts_with($status, 'failed') => DonationOrder::STATUS_FAILED,
            $status === 'pending' => DonationOrder::STATUS_PENDING,
            default => DonationOrder::STATUS_FAILED,
        };
    }

    public function mapCauseSlug(string $donateFor): ?string
    {
        $key = strtolower(trim($donateFor));

        if ($key === '') {
            return null;
        }

        return self::CAUSE_SLUG_MAP[$key] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseDonationRows(string $sql): array
    {
        $rows = [];
        $offset = 0;

        while (($insertPos = strpos($sql, 'INSERT INTO `donations`', $offset)) !== false) {
            $valuesPos = stripos($sql, 'VALUES', $insertPos);
            if ($valuesPos === false) {
                break;
            }

            $nextInsert = strpos($sql, 'INSERT INTO `donations`', $valuesPos + 6);
            $nextCreate = strpos($sql, 'CREATE TABLE', $valuesPos + 6);

            $endCandidates = array_filter([$nextInsert, $nextCreate], fn ($pos) => $pos !== false);
            $end = $endCandidates === [] ? strlen($sql) : min($endCandidates);

            $chunk = substr($sql, $valuesPos + 6, $end - ($valuesPos + 6));

            foreach ($this->extractValueTuples($chunk) as $tupleSql) {
                $values = $this->parseSqlValueList($tupleSql);

                if (count($values) < 19 || ! is_numeric($values[0])) {
                    continue;
                }

                $rows[] = [
                    'id' => (int) $values[0],
                    'payment_id' => $values[1],
                    'razorpay_order_id' => $values[2],
                    'receipt_no' => $values[3],
                    'donor_name' => $values[4],
                    'amount' => $values[5],
                    'email' => $values[6],
                    'contact' => $values[7],
                    'donate_for' => $values[8],
                    'pan_number' => $values[9],
                    'address' => $values[10],
                    'status' => $values[11],
                    'payment_link_id' => $values[12],
                    'payment_link_url' => $values[13],
                    'created_at' => $values[14],
                    'updated_at' => $values[15],
                    'transferred_amount' => $values[16],
                    'transfer_status' => $values[17],
                    'transferred_at' => $values[18],
                ];
            }

            $offset = $end;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function extractValueTuples(string $chunk): array
    {
        $tuples = [];
        $length = strlen($chunk);
        $i = 0;

        while ($i < $length) {
            if ($chunk[$i] !== '(') {
                $i++;

                continue;
            }

            $depth = 0;
            $inString = false;
            $escape = false;
            $start = $i;

            for (; $i < $length; $i++) {
                $char = $chunk[$i];

                if ($inString) {
                    if ($escape) {
                        $escape = false;

                        continue;
                    }

                    if ($char === '\\') {
                        $escape = true;

                        continue;
                    }

                    if ($char === "'") {
                        // SQL escaped quote ''
                        if ($i + 1 < $length && $chunk[$i + 1] === "'") {
                            $i++;

                            continue;
                        }

                        $inString = false;
                    }

                    continue;
                }

                if ($char === "'") {
                    $inString = true;

                    continue;
                }

                if ($char === '(') {
                    $depth++;

                    continue;
                }

                if ($char === ')') {
                    $depth--;

                    if ($depth === 0) {
                        $tuples[] = substr($chunk, $start + 1, $i - $start - 1);
                        $i++;

                        break;
                    }
                }
            }
        }

        return $tuples;
    }

    /**
     * @return list<mixed>
     */
    private function parseSqlValueList(string $tupleSql): array
    {
        $values = [];
        $length = strlen($tupleSql);
        $buffer = '';
        $inString = false;
        $escape = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $tupleSql[$i];

            if ($inString) {
                if ($escape) {
                    $buffer .= $char;
                    $escape = false;

                    continue;
                }

                if ($char === '\\') {
                    $escape = true;

                    continue;
                }

                if ($char === "'") {
                    if ($i + 1 < $length && $tupleSql[$i + 1] === "'") {
                        $buffer .= "'";
                        $i++;

                        continue;
                    }

                    $inString = false;

                    continue;
                }

                $buffer .= $char;

                continue;
            }

            if ($char === "'") {
                $inString = true;

                continue;
            }

            if ($char === ',') {
                $values[] = $this->castSqlToken(trim($buffer));
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '' || $buffer === '0') {
            $values[] = $this->castSqlToken(trim($buffer));
        }

        return $values;
    }

    private function castSqlToken(string $token): mixed
    {
        if (strcasecmp($token, 'NULL') === 0) {
            return null;
        }

        if (is_numeric($token)) {
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }

        return $token;
    }

    private function normalizePhone(mixed $contact, string $email = '', int $legacyId = 0): string
    {
        $raw = trim((string) ($contact ?? ''));
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (strlen($digits) > 10 && str_starts_with($digits, '91')) {
            $digits = substr($digits, -10);
        }

        if ($digits === '') {
            return 'u-'.substr(md5($email.'|'.$legacyId.'|'.$raw), 0, 12);
        }

        return $digits;
    }

    private function normalizePan(mixed $pan): ?string
    {
        $value = strtoupper(trim((string) ($pan ?? '')));

        if ($value === '' || $value === 'N/A' || $value === 'NA') {
            return null;
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Carbon::parse((string) $value, config('app.timezone'));
    }
}
