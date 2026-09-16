<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'label', 'description', 'group'];

    public const SEND_RECEIPT_EMAIL = 'send_receipt_email';

    public const SEND_WHATSAPP_THANK_YOU = 'send_whatsapp_thank_you';

    public const SEND_DONATION_CERTIFICATE = 'send_donation_certificate';

    public const SEND_RECEIPT_WHATSAPP = 'send_receipt_whatsapp';

    public const SEND_WHATSAPP_PAYMENT_LINK = 'send_whatsapp_payment_link';

    public const SEND_BIRTHDAY_WHATSAPP = 'send_birthday_whatsapp';

    public const ATTACH_RECEIPT_PDF = 'attach_receipt_pdf';

    public const LOG_FAILED_DONATIONS_TO_SHEET = 'log_failed_donations_to_sheet';

    public const AISENSY_BIRTHDAY_CAMPAIGN = 'aisensy_birthday_campaign';

    public const AISENSY_BIRTHDAY_ACCOUNT_ID = 'aisensy_birthday_account_id';

    public const AISENSY_OTP_CAMPAIGN = 'aisensy_otp_campaign';

    public const AISENSY_OTP_ACCOUNT_ID = 'aisensy_otp_account_id';

    public const RECONCILE_NOTIFICATION_RETRY_HOURS = 'reconcile_notification_retry_hours';

    public const RECONCILE_NOTIFICATION_MAX_ATTEMPTS = 'reconcile_notification_max_attempts';

    public const NOTIFICATION_JOB_TRIES = 'notification_job_tries';

    public const NOTIFICATION_JOB_BACKOFF_SECONDS = 'notification_job_backoff_seconds';

    public const BRANDING_OVERRIDES = 'branding_overrides';

    /**
     * @return list<string>
     */
    public static function toggleKeys(): array
    {
        return [
            self::SEND_RECEIPT_EMAIL,
            self::SEND_WHATSAPP_THANK_YOU,
            self::SEND_DONATION_CERTIFICATE,
            self::SEND_RECEIPT_WHATSAPP,
            self::SEND_WHATSAPP_PAYMENT_LINK,
            self::SEND_BIRTHDAY_WHATSAPP,
            self::ATTACH_RECEIPT_PDF,
            self::LOG_FAILED_DONATIONS_TO_SHEET,
        ];
    }

    /**
     * Integer settings editable from admin (stored as string values).
     *
     * @return array<string, array{min: int, max: int}>
     */
    public static function integerSettingRules(): array
    {
        return [
            self::RECONCILE_NOTIFICATION_RETRY_HOURS => ['min' => 1, 'max' => 720],
            self::RECONCILE_NOTIFICATION_MAX_ATTEMPTS => ['min' => 1, 'max' => 50],
            self::NOTIFICATION_JOB_TRIES => ['min' => 1, 'max' => 20],
            self::NOTIFICATION_JOB_BACKOFF_SECONDS => ['min' => 1, 'max' => 3600],
        ];
    }

    public static function isToggleKey(string $key): bool
    {
        return in_array($key, self::toggleKeys(), true);
    }

    public static function isIntegerKey(string $key): bool
    {
        return array_key_exists($key, self::integerSettingRules());
    }

    /**
     * Returns true if the setting is enabled (value is truthy).
     * Defaults to $default if the key does not exist in the database.
     */
    public static function isEnabled(string $key, bool $default = true): bool
    {
        $setting = static::where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return (bool) $setting->value;
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $setting = static::query()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        $value = trim((string) $setting->value);

        return $value === '' ? $default : $value;
    }

    public static function getIntValue(string $key, int $default): int
    {
        $raw = static::getValue($key);

        if ($raw === null || ! is_numeric($raw)) {
            return $default;
        }

        $value = (int) $raw;
        $rules = self::integerSettingRules()[$key] ?? null;

        if ($rules === null) {
            return $value;
        }

        return max($rules['min'], min($rules['max'], $value));
    }
}
