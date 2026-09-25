<?php

namespace App\Models;

use App\Jobs\LogFailedDonationFollowUpSheetJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DonationOrder extends Model
{
    use HasFactory;

    protected $table = 'donation_orders';

    protected $fillable = [
        'order_uuid',
        'payment_provider',
        'provider_order_id',
        'provider_payment_id',
        'donor_id',
        'created_by',
        'donation_subscription_id',
        'billing_cycle_number',
        'is_recurring',

        'source_channel',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'attr_source',
        'attr_medium',
        'attr_platform',
        'attr_placement',
        'partner_user_id',
        'partner_code',
        'meta_campaign_id',
        'meta_adset_id',
        'meta_ad_id',
        'referrer',
        'landing_path',
        'device_type',
        'ip_address',
        'ip_country_code',
        'ip_country_name',
        'ip_region_name',
        'ip_city',
        'ip_postal_code',
        'ip_lat',
        'ip_lng',
        'ip_timezone',
        'ip_asn',
        'ip_isp',
        'source_campaign_id',

        'donor_name',
        'donor_email',
        'donor_phone',
        'date_of_birth',
        'pan_number',
        'address',
        'pincode',
        'city',
        'state',
        'country',
        'donor_country_code',
        'consent_indian_citizen',

        'currency',
        'total_amount',
        'status',
        'receipt_number',

        // tracking / recovery fields
        'paid_at',
        'failed_at',
        'receipt_sent_at',
        'sheet_logged_at',
        'failed_sheet_logged_at',
        'whatsapp_sent_at',
        'certificate_whatsapp_sent_at',
        'receipt_whatsapp_sent_at',
        'receipt_path',
        'receipt_failed_at',
        'receipt_last_error',
        'receipt_notify_attempts',
        'sheet_notify_attempts',
        'whatsapp_notify_attempts',
        'certificate_whatsapp_notify_attempts',
        'receipt_whatsapp_notify_attempts',
        'whatsapp_failed_at',
        'whatsapp_last_error',
        'certificate_whatsapp_failed_at',
        'certificate_whatsapp_last_error',
        'receipt_whatsapp_failed_at',
        'receipt_whatsapp_last_error',

        'payment_link_id',
        'payment_link_url',
        'payment_link_sent_at',
        'payment_link_email_sent_at',
        'payment_link_sms_sent_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'consent_indian_citizen' => 'boolean',
        'is_recurring' => 'boolean',
        'ip_lat' => 'float',
        'ip_lng' => 'float',
        'ip_asn' => 'integer',

        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'receipt_sent_at' => 'datetime',
        'receipt_failed_at' => 'datetime',
        'sheet_logged_at' => 'datetime',
        'failed_sheet_logged_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        'certificate_whatsapp_sent_at' => 'datetime',
        'receipt_whatsapp_sent_at' => 'datetime',
        'whatsapp_failed_at' => 'datetime',
        'certificate_whatsapp_failed_at' => 'datetime',
        'receipt_whatsapp_failed_at' => 'datetime',
        'payment_link_sent_at' => 'datetime',
        'payment_link_email_sent_at' => 'datetime',
        'payment_link_sms_sent_at' => 'datetime',
        'receipt_notify_attempts' => 'integer',
        'sheet_notify_attempts' => 'integer',
        'whatsapp_notify_attempts' => 'integer',
        'certificate_whatsapp_notify_attempts' => 'integer',
        'receipt_whatsapp_notify_attempts' => 'integer',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'order_uuid';
    }

    /**
     * Automatically generate UUID when creating order
     */
    protected static function booted(): void
    {
        static::creating(function ($order) {
            if (empty($order->order_uuid)) {
                $order->order_uuid = (string) Str::uuid();
            }
        });
    }

    public function scopeWhereActivityBetween(Builder $query, \Illuminate\Support\Carbon $start, \Illuminate\Support\Carbon $end): Builder
    {
        return $query->where(function (Builder $activityQuery) use ($start, $end): void {
            $activityQuery
                ->whereBetween('paid_at', [$start, $end])
                ->orWhere(function (Builder $unpaidQuery) use ($start, $end): void {
                    $unpaidQuery
                        ->whereNull('paid_at')
                        ->whereBetween('created_at', [$start, $end]);
                });
        });
    }

    public function scopeWhereActivityOnOrAfter(Builder $query, \Illuminate\Support\Carbon $start): Builder
    {
        return $query->where(function (Builder $activityQuery) use ($start): void {
            $activityQuery
                ->where('paid_at', '>=', $start)
                ->orWhere(function (Builder $unpaidQuery) use ($start): void {
                    $unpaidQuery
                        ->whereNull('paid_at')
                        ->where('created_at', '>=', $start);
                });
        });
    }

    /* ==========================
     |  Relationships
     ========================== */

    public function items(): HasMany
    {
        return $this->hasMany(DonationItem::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(DonationSubscription::class, 'donation_subscription_id');
    }

    public function sourceCampaign(): BelongsTo
    {
        return $this->belongsTo(DonationCampaign::class, 'source_campaign_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function linkTrackingVisits(): HasMany
    {
        return $this->hasMany(LinkTrackingVisit::class);
    }

    /* ==========================
     |  Status Helpers
     ========================== */

    public function markAsPaid(?string $providerPaymentId = null, ?\DateTimeInterface $paidAt = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'provider_payment_id' => $providerPaymentId,
            'paid_at' => $paidAt ?? now(),
            'failed_at' => null,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        LogFailedDonationFollowUpSheetJob::dispatch($this);
    }

    /* ==========================
     |  State Helpers
     ========================== */

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Donations created from Admin → Record offline (any payment provider).
     */
    public function isManualAdminEntry(): bool
    {
        if ($this->payment_provider === self::PROVIDER_OFFLINE) {
            return true;
        }

        if ($this->source_channel === 'offline') {
            return true;
        }

        return str_starts_with((string) $this->provider_order_id, 'manual-');
    }

    /**
     * Paid donations may have donor contact corrected by admin.
     * Amount / payment IDs stay locked unless this is a manual offline entry.
     */
    public function allowsAdminDonorEdit(): bool
    {
        return $this->isPaid();
    }

    public function allowsAdminAmountEdit(): bool
    {
        return $this->isManualAdminEntry();
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeAbandonedCheckouts(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED])
            ->whereNull('paid_at')
            ->whereNotNull('provider_order_id');
    }

    public function hasReceipt(): bool
    {
        return ! empty($this->receipt_number);
    }

    public function receiptNumberFormatted(): string
    {
        if (! $this->hasReceipt()) {
            return '';
        }

        return self::formatReceiptNumber($this->receipt_number, $this->payment_provider);
    }

    public static function formatReceiptNumber(int|string $receiptNumber, ?string $provider = null): string
    {
        return self::receiptPrefix($provider).$receiptNumber;
    }

    public static function receiptPrefix(?string $provider = null): string
    {
        return match (self::receiptProviderFamily($provider)) {
            self::PROVIDER_OFFLINE => 'MSCT-OFF-',
            self::PROVIDER_DANAMOJO => 'MSCT-DNMJ-',
            self::PROVIDER_CASHFREE => 'MSCT-CF-',
            default => 'MSCT-RZP-',
        };
    }

    public static function receiptSequenceName(?string $provider = null): string
    {
        return 'donation_orders_'.self::receiptProviderFamily($provider);
    }

    public static function receiptProviderFamily(?string $provider = null): string
    {
        return match ($provider) {
            self::PROVIDER_OFFLINE => self::PROVIDER_OFFLINE,
            self::PROVIDER_DANAMOJO => self::PROVIDER_DANAMOJO,
            self::PROVIDER_CASHFREE => self::PROVIDER_CASHFREE,
            self::PROVIDER_RAZORPAY_QR, self::PROVIDER_RAZORPAY => self::PROVIDER_RAZORPAY,
            default => self::PROVIDER_RAZORPAY,
        };
    }

    /**
     * @return list<string>
     */
    public static function receiptProviderFamilyMembers(?string $provider = null): array
    {
        $family = self::receiptProviderFamily($provider);

        return match ($family) {
            self::PROVIDER_RAZORPAY => [self::PROVIDER_RAZORPAY, self::PROVIDER_RAZORPAY_QR],
            default => [$family],
        };
    }

    public function isReceiptSent(): bool
    {
        return ! is_null($this->receipt_sent_at);
    }

    public function receiptFailed(): bool
    {
        return ! is_null($this->receipt_failed_at);
    }

    public function receiptStatus(): string
    {
        if ($this->receipt_sent_at) {
            return 'sent';
        }

        if ($this->receipt_failed_at) {
            return 'failed';
        }

        if ($this->paid_at) {
            return 'generated';
        }

        return 'pending';
    }

    /* ==========================
     |  Constants
     ========================== */

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const PROVIDER_RAZORPAY = 'razorpay';

    public const PROVIDER_RAZORPAY_QR = 'razorpay_qr';

    public const PROVIDER_CASHFREE = 'cashfree';

    public const PROVIDER_DANAMOJO = 'danamojo';

    public const PROVIDER_OFFLINE = 'offline';

    /**
     * @return array<string, string>
     */
    public static function paymentProviderOptions(): array
    {
        return [
            self::PROVIDER_OFFLINE => 'Offline (cash / bank / manual)',
            self::PROVIDER_RAZORPAY => 'Razorpay',
            self::PROVIDER_RAZORPAY_QR => 'Razorpay QR',
            self::PROVIDER_DANAMOJO => 'Danamojo',
            self::PROVIDER_CASHFREE => 'Cashfree',
        ];
    }

    public static function providerDisplayName(?string $provider): string
    {
        return match ($provider) {
            self::PROVIDER_RAZORPAY => 'Razorpay',
            self::PROVIDER_RAZORPAY_QR => 'Razorpay QR',
            self::PROVIDER_OFFLINE => 'Offline',
            self::PROVIDER_DANAMOJO => 'Danamojo',
            self::PROVIDER_CASHFREE => 'Cashfree',
            null, '' => 'Unknown',
            default => ucfirst($provider),
        };
    }
}
