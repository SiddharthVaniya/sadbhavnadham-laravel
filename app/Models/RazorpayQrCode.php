<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RazorpayQrCode extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const USAGE_SINGLE = 'single_use';

    public const USAGE_MULTIPLE = 'multiple_use';

    public const TYPE_UPI = 'upi_qr';

    protected $fillable = [
        'qr_uuid',
        'razorpay_qr_code_id',
        'name',
        'description',
        'type',
        'usage',
        'fixed_amount',
        'payment_amount_paise',
        'status',
        'image_url',
        'payments_count_received',
        'payments_amount_received_paise',
        'close_reason',
        'closed_at',
        'razorpay_created_at',
        'meta',
        'created_by',
        'cause_id',
        'cause_package_id',
    ];

    protected function casts(): array
    {
        return [
            'fixed_amount' => 'boolean',
            'payment_amount_paise' => 'integer',
            'payments_count_received' => 'integer',
            'payments_amount_received_paise' => 'integer',
            'closed_at' => 'datetime',
            'razorpay_created_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $qr): void {
            if (empty($qr->qr_uuid)) {
                $qr->qr_uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'qr_uuid';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Cause::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CausePackage::class, 'cause_package_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function paymentAmountRupees(): ?float
    {
        if ($this->payment_amount_paise === null) {
            return null;
        }

        return round(((int) $this->payment_amount_paise) / 100, 2);
    }

    public function paymentsAmountReceivedRupees(): float
    {
        return round(((int) $this->payments_amount_received_paise) / 100, 2);
    }

    public function usageLabel(): string
    {
        return match ($this->usage) {
            self::USAGE_SINGLE => 'Single use',
            self::USAGE_MULTIPLE => 'Multiple use',
            default => ucfirst(str_replace('_', ' ', (string) $this->usage)),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_CLOSED => 'Closed',
            default => ucfirst((string) $this->status),
        };
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
