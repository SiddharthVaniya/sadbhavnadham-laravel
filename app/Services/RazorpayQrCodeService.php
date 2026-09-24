<?php

namespace App\Services;

use App\Models\RazorpayQrCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use RuntimeException;
use Throwable;

class RazorpayQrCodeService
{
    /**
     * @param  array{
     *     name: string,
     *     description?: ?string,
     *     usage?: string,
     *     fixed_amount?: bool,
     *     payment_amount?: float|int|null,
     * }  $input
     */
    public function create(array $input, ?User $actor = null): RazorpayQrCode
    {
        $fixedAmount = (bool) ($input['fixed_amount'] ?? false);
        $paymentAmountRupees = $input['payment_amount'] ?? null;

        $payload = [
            'type' => RazorpayQrCode::TYPE_UPI,
            'name' => trim((string) $input['name']),
            'usage' => ($input['usage'] ?? RazorpayQrCode::USAGE_MULTIPLE) === RazorpayQrCode::USAGE_SINGLE
                ? RazorpayQrCode::USAGE_SINGLE
                : RazorpayQrCode::USAGE_MULTIPLE,
            'fixed_amount' => $fixedAmount,
            'description' => filled($input['description'] ?? null)
                ? trim((string) $input['description'])
                : null,
            'notes' => [
                'source' => 'sadbhavnadham_admin',
            ],
        ];

        if ($fixedAmount) {
            $rupees = (float) $paymentAmountRupees;
            if ($rupees < 1) {
                throw new RuntimeException('Fixed-amount QR requires payment amount of at least ₹1.');
            }
            $payload['payment_amount'] = (int) round($rupees * 100);
        }

        $payload = array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        try {
            $entity = $this->entityToArray($this->api()->qrCode->create($payload));
        } catch (Throwable $e) {
            Log::error('Razorpay QR create failed', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);

            throw new RuntimeException('Razorpay could not create the QR code: '.$e->getMessage(), 0, $e);
        }

        $qr = $this->upsertFromEntity($entity, $actor?->id);

        $causeId = filled($input['cause_id'] ?? null) ? (int) $input['cause_id'] : null;

        $qr->update([
            'cause_id' => $causeId,
            'cause_package_id' => $causeId && filled($input['cause_package_id'] ?? null)
                ? (int) $input['cause_package_id']
                : null,
        ]);

        return $qr->fresh(['cause', 'package']);
    }

    /**
     * Local-only mapping (does not call Razorpay).
     *
     * @param  array{cause_id?: int|null, cause_package_id?: int|null, name?: string, description?: ?string}  $input
     */
    public function updateLocalMapping(RazorpayQrCode $qr, array $input): RazorpayQrCode
    {
        $updates = [];

        if (array_key_exists('cause_id', $input)) {
            $causeId = $input['cause_id'] ? (int) $input['cause_id'] : null;
            $updates['cause_id'] = $causeId;
            $updates['cause_package_id'] = $causeId && ! empty($input['cause_package_id'])
                ? (int) $input['cause_package_id']
                : null;
        } elseif (array_key_exists('cause_package_id', $input)) {
            $updates['cause_package_id'] = $input['cause_package_id'] && $qr->cause_id
                ? (int) $input['cause_package_id']
                : null;
        }

        if (array_key_exists('name', $input) && filled($input['name'])) {
            $updates['name'] = trim((string) $input['name']);
        }

        if (array_key_exists('description', $input)) {
            $updates['description'] = filled($input['description'] ?? null)
                ? trim((string) $input['description'])
                : null;
        }

        if ($updates !== []) {
            $qr->update($updates);
        }

        return $qr->fresh(['cause', 'package']);
    }

    public function close(RazorpayQrCode $qr): RazorpayQrCode
    {
        if ($qr->isClosed()) {
            return $qr;
        }

        try {
            $entity = $this->entityToArray(
                $this->api()->qrCode->fetch($qr->razorpay_qr_code_id)->close()
            );
        } catch (Throwable $e) {
            Log::error('Razorpay QR close failed', [
                'razorpay_qr_code_id' => $qr->razorpay_qr_code_id,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Razorpay could not close the QR code: '.$e->getMessage(), 0, $e);
        }

        return $this->upsertFromEntity($entity, $qr->created_by);
    }

    public function syncOne(RazorpayQrCode $qr): RazorpayQrCode
    {
        try {
            $entity = $this->entityToArray(
                $this->api()->qrCode->fetch($qr->razorpay_qr_code_id)
            );
        } catch (Throwable $e) {
            Log::error('Razorpay QR fetch failed', [
                'razorpay_qr_code_id' => $qr->razorpay_qr_code_id,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Razorpay could not fetch the QR code: '.$e->getMessage(), 0, $e);
        }

        return $this->upsertFromEntity($entity, $qr->created_by);
    }

    /**
     * @return array{synced: int, created: int, updated: int}
     */
    public function syncAll(int $pageSize = 100): array
    {
        $synced = 0;
        $created = 0;
        $updated = 0;
        $skip = 0;

        do {
            try {
                $collection = $this->entityToArray(
                    $this->api()->qrCode->all([
                        'count' => $pageSize,
                        'skip' => $skip,
                    ])
                );
            } catch (Throwable $e) {
                Log::error('Razorpay QR list failed', [
                    'skip' => $skip,
                    'message' => $e->getMessage(),
                ]);

                throw new RuntimeException('Razorpay could not list QR codes: '.$e->getMessage(), 0, $e);
            }

            $items = $collection['items'] ?? [];
            if (! is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                if (! is_array($item) || empty($item['id'])) {
                    continue;
                }

                $exists = RazorpayQrCode::query()
                    ->where('razorpay_qr_code_id', $item['id'])
                    ->exists();

                $this->upsertFromEntity($item);
                $synced++;
                if ($exists) {
                    $updated++;
                } else {
                    $created++;
                }
            }

            $count = count($items);
            $skip += $count;
        } while ($count >= $pageSize);

        return compact('synced', 'created', 'updated');
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function upsertFromEntity(array $entity, ?int $createdBy = null): RazorpayQrCode
    {
        $razorpayId = (string) ($entity['id'] ?? '');
        if ($razorpayId === '') {
            throw new RuntimeException('Razorpay QR entity missing id.');
        }

        $attributes = [
            'name' => (string) ($entity['name'] ?? $razorpayId),
            'description' => isset($entity['description']) ? (string) $entity['description'] : null,
            'type' => (string) ($entity['type'] ?? RazorpayQrCode::TYPE_UPI),
            'usage' => (string) ($entity['usage'] ?? RazorpayQrCode::USAGE_MULTIPLE),
            'fixed_amount' => $this->isTruthy($entity['fixed_amount'] ?? false),
            'payment_amount_paise' => isset($entity['payment_amount'])
                ? (int) $entity['payment_amount']
                : null,
            'status' => (string) ($entity['status'] ?? RazorpayQrCode::STATUS_ACTIVE),
            'image_url' => isset($entity['image_url']) ? (string) $entity['image_url'] : null,
            'payments_count_received' => (int) ($entity['payments_count_received'] ?? 0),
            'payments_amount_received_paise' => (int) ($entity['payments_amount_received'] ?? 0),
            'close_reason' => isset($entity['close_reason']) ? (string) $entity['close_reason'] : null,
            'closed_at' => $this->timestamp($entity['closed_at'] ?? null),
            'razorpay_created_at' => $this->timestamp($entity['created_at'] ?? null),
            'meta' => [
                'notes' => $entity['notes'] ?? null,
                'customer_id' => $entity['customer_id'] ?? null,
                'close_by' => $entity['close_by'] ?? null,
            ],
        ];

        $qr = RazorpayQrCode::query()->updateOrCreate(
            ['razorpay_qr_code_id' => $razorpayId],
            $attributes
        );

        if ($createdBy && ! $qr->created_by) {
            $qr->update(['created_by' => $createdBy]);
        }

        return $qr->fresh();
    }

    private function api(): Api
    {
        return new Api(
            config('payments.razorpay.key'),
            config('payments.razorpay.secret')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function entityToArray(mixed $entity): array
    {
        if (is_array($entity)) {
            return $entity;
        }

        if (is_object($entity) && method_exists($entity, 'toArray')) {
            return $entity->toArray();
        }

        return json_decode(json_encode($entity), true) ?? [];
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value)->setTimezone(config('app.timezone'));
        }

        return Carbon::parse((string) $value)->setTimezone(config('app.timezone'));
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true'], true);
    }
}
