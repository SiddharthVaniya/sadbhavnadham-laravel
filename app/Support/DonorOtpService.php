<?php

namespace App\Support;

use App\Mail\DonorOtpMail;
use App\Models\AisensyAccount;
use App\Models\Donor;
use App\Models\Setting;
use App\Services\AiSensyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class DonorOtpService
{
    public function __construct(private AiSensyService $aiSensyService) {}

    public function findDonorByPhone(string $phone, ?string $dialCode = null): ?Donor
    {
        $candidates = $dialCode === null
            ? array_values(array_filter([$this->normalizePhone($phone)]))
            : PhoneDialCodes::lookupCandidates($phone, $dialCode);

        if ($candidates === []) {
            return null;
        }

        return Donor::query()
            ->whereIn('phone', $candidates)
            ->orderByDesc('last_donated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{found: bool, sent?: bool, channel?: string, destination?: string, message: string, cooldown_seconds?: int}
     */
    public function send(string $phone, string $dialCode = '91'): array
    {
        $phone = $this->normalizePhone($phone);
        $dialCode = preg_replace('/\D+/', '', $dialCode) ?: '91';
        $canonical = PhoneDialCodes::storePhone($phone, $dialCode);
        $donor = $this->findDonorByPhone($phone, $dialCode);

        if ($donor === null) {
            return [
                'found' => false,
                'message' => 'We could not find a saved profile for this number. Continue as a guest to complete your donation.',
            ];
        }

        $cachePhone = $canonical !== '' ? $canonical : (string) $donor->phone;
        $cooldownRemaining = $this->cooldownRemainingSeconds($cachePhone);

        if ($cooldownRemaining > 0) {
            return [
                'found' => true,
                'sent' => false,
                'message' => 'Please wait before requesting another code.',
                'cooldown_seconds' => $cooldownRemaining,
            ];
        }

        $dailyLimit = (int) config('donation.otp.daily_send_limit', 10);
        $dailyCount = (int) Cache::get($this->dailySendKey($cachePhone), 0);

        if ($dailyCount >= $dailyLimit) {
            return [
                'found' => true,
                'sent' => false,
                'message' => 'Daily verification limit reached for this number. Please try again tomorrow, or continue as a guest.',
            ];
        }

        $otp = $this->generateOtp();
        $ttl = (int) config('donation.otp.ttl_seconds', 300);
        $cooldownSeconds = (int) config('donation.otp.resend_seconds', 60);

        Cache::put($this->otpKey($cachePhone), [
            'hash' => Hash::make($otp),
            'attempts' => 0,
            'donor_id' => $donor->id,
        ], $ttl);

        Cache::put($this->cooldownKey($cachePhone), now()->addSeconds($cooldownSeconds)->timestamp, $cooldownSeconds);

        $delivery = $this->deliver($donor, $otp);

        if (! $delivery['sent']) {
            Cache::forget($this->otpKey($cachePhone));
            Cache::forget($this->cooldownKey($cachePhone));

            return [
                'found' => true,
                'sent' => false,
                'message' => $delivery['message'],
            ];
        }

        Cache::put($this->dailySendKey($cachePhone), $dailyCount + 1, now()->endOfDay());

        if (config('app.debug')) {
            Log::debug('Donor OTP generated', [
                'phone' => $cachePhone,
                'channel' => $delivery['channel'],
            ]);
        }

        return [
            'found' => true,
            'sent' => true,
            'channel' => $delivery['channel'],
            'destination' => $delivery['destination'],
            'message' => $delivery['message'],
            'cooldown_seconds' => $cooldownSeconds,
            'lookup_phone' => $cachePhone,
        ];
    }

    public function findDonorByEmail(string $email): ?Donor
    {
        $email = $this->normalizeEmail($email);

        if ($email === '') {
            return null;
        }

        return Donor::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderByDesc('last_donated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{found: bool, sent?: bool, channel?: string, destination?: string, message: string, cooldown_seconds?: int}
     */
    public function sendByEmail(string $email): array
    {
        $email = $this->normalizeEmail($email);
        $donor = $this->findDonorByEmail($email);

        if ($donor === null) {
            return [
                'found' => false,
                'message' => 'We could not find a saved profile for this email. Continue as a guest to complete your donation.',
            ];
        }

        $cacheKey = $this->emailIdentityKey($email);
        $cooldownRemaining = $this->cooldownRemainingSeconds($cacheKey);

        if ($cooldownRemaining > 0) {
            return [
                'found' => true,
                'sent' => false,
                'message' => 'Please wait before requesting another code.',
                'cooldown_seconds' => $cooldownRemaining,
            ];
        }

        $dailyLimit = (int) config('donation.otp.daily_send_limit', 10);
        $dailyCount = (int) Cache::get($this->dailySendKey($cacheKey), 0);

        if ($dailyCount >= $dailyLimit) {
            return [
                'found' => true,
                'sent' => false,
                'message' => 'Daily verification limit reached for this email. Please try again tomorrow, or continue as a guest.',
            ];
        }

        $otp = $this->generateOtp();
        $ttl = (int) config('donation.otp.ttl_seconds', 300);
        $cooldownSeconds = (int) config('donation.otp.resend_seconds', 60);

        Cache::put($this->otpKey($cacheKey), [
            'hash' => Hash::make($otp),
            'attempts' => 0,
            'donor_id' => $donor->id,
            'login_method' => 'email',
        ], $ttl);

        Cache::put($this->cooldownKey($cacheKey), now()->addSeconds($cooldownSeconds)->timestamp, $cooldownSeconds);

        $delivery = $this->deliverEmail($donor, $otp, $email);

        if (! $delivery['sent']) {
            Cache::forget($this->otpKey($cacheKey));
            Cache::forget($this->cooldownKey($cacheKey));

            return [
                'found' => true,
                'sent' => false,
                'message' => $delivery['message'],
            ];
        }

        Cache::put($this->dailySendKey($cacheKey), $dailyCount + 1, now()->endOfDay());

        if (config('app.debug')) {
            Log::debug('Donor OTP generated', [
                'email' => $email,
                'channel' => 'email',
            ]);
        }

        return [
            'found' => true,
            'sent' => true,
            'channel' => 'email',
            'destination' => $delivery['destination'],
            'message' => $delivery['message'],
            'cooldown_seconds' => $cooldownSeconds,
            'login_method' => 'email',
        ];
    }

    /**
     * @return array{verified: bool, profile?: array<string, mixed>, message?: string, donor?: Donor}
     */
    public function verifyByEmail(string $email, string $otp): array
    {
        $email = $this->normalizeEmail($email);
        $otp = trim($otp);
        $identity = $this->emailIdentityKey($email);
        $cacheKey = $this->otpKey($identity);
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || empty($payload['hash'])) {
            throw ValidationException::withMessages([
                'otp' => 'This code has expired. Please request a new one.',
            ]);
        }

        $maxAttempts = (int) config('donation.otp.max_attempts', 5);
        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= $maxAttempts) {
            Cache::forget($cacheKey);

            throw ValidationException::withMessages([
                'otp' => 'Too many incorrect attempts. Please request a new code.',
            ]);
        }

        if (! Hash::check($otp, (string) $payload['hash'])) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($cacheKey, $payload, (int) config('donation.otp.ttl_seconds', 300));

            throw ValidationException::withMessages([
                'otp' => 'Incorrect code. Please try again.',
            ]);
        }

        Cache::forget($cacheKey);
        Cache::forget($this->cooldownKey($identity));

        $donor = Donor::query()->find($payload['donor_id'] ?? null) ?? $this->findDonorByEmail($email);

        if ($donor === null) {
            throw ValidationException::withMessages([
                'otp' => 'Donor profile could not be loaded. Please continue as a guest.',
            ]);
        }

        return [
            'verified' => true,
            'donor' => $donor,
            'profile' => $this->profilePayload($donor),
            'message' => 'Verified. Your saved details are ready.',
        ];
    }

    /**
     * @return array{verified: bool, profile?: array<string, mixed>, message?: string, donor?: Donor}
     */
    public function verify(string $phone, string $otp, string $dialCode = '91'): array
    {
        $phone = $this->normalizePhone($phone);
        $dialCode = preg_replace('/\D+/', '', $dialCode) ?: '91';
        $otp = trim($otp);
        $canonical = PhoneDialCodes::storePhone($phone, $dialCode);
        $candidates = PhoneDialCodes::lookupCandidates($phone, $dialCode);
        $cacheKey = null;
        $payload = null;

        foreach (array_values(array_unique(array_filter([$canonical, ...$candidates]))) as $candidate) {
            $key = $this->otpKey($candidate);
            $cached = Cache::get($key);

            if (is_array($cached) && ! empty($cached['hash'])) {
                $cacheKey = $key;
                $payload = $cached;
                $canonical = $candidate;
                break;
            }
        }

        if ($cacheKey === null || ! is_array($payload) || empty($payload['hash'])) {
            throw ValidationException::withMessages([
                'otp' => 'This code has expired. Please request a new one.',
            ]);
        }

        $maxAttempts = (int) config('donation.otp.max_attempts', 5);
        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= $maxAttempts) {
            Cache::forget($cacheKey);

            throw ValidationException::withMessages([
                'otp' => 'Too many incorrect attempts. Please request a new code.',
            ]);
        }

        if (! Hash::check($otp, (string) $payload['hash'])) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($cacheKey, $payload, (int) config('donation.otp.ttl_seconds', 300));

            throw ValidationException::withMessages([
                'otp' => 'Incorrect code. Please try again.',
            ]);
        }

        Cache::forget($cacheKey);
        Cache::forget($this->cooldownKey($canonical));

        $donor = Donor::query()->find($payload['donor_id'] ?? null) ?? $this->findDonorByPhone($phone, $dialCode);

        if ($donor === null) {
            throw ValidationException::withMessages([
                'otp' => 'Donor profile could not be loaded. Please continue as a guest.',
            ]);
        }

        return [
            'verified' => true,
            'donor' => $donor,
            'profile' => $this->profilePayload($donor),
            'message' => 'Verified. Your saved details are ready.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(Donor $donor): array
    {
        return [
            'donor_name' => (string) ($donor->name ?? ''),
            'donor_email' => (string) ($donor->email ?? ''),
            'donor_phone' => (string) ($donor->phone ?? ''),
            'date_of_birth' => $donor->date_of_birth?->format('Y-m-d'),
            'address' => (string) ($donor->address ?? ''),
            'pincode' => (string) ($donor->pincode ?? ''),
            'city' => (string) ($donor->city ?? ''),
            'state' => (string) ($donor->state ?? ''),
            'country' => (string) ($donor->country ?: 'INDIA'),
            'pan_number' => (string) ($donor->pan_number ?? ''),
        ];
    }

    /**
     * @return array{sent: bool, channel?: string, destination?: string, message: string}
     */
    private function deliver(Donor $donor, string $otp): array
    {
        $whatsappSent = $this->sendWhatsAppOtp($donor, $otp);

        if ($whatsappSent) {
            return [
                'sent' => true,
                'channel' => 'whatsapp',
                'destination' => $this->maskPhone((string) $donor->phone),
                'message' => 'We sent a code on WhatsApp to '.$this->maskPhone((string) $donor->phone).'.',
            ];
        }

        return $this->deliverEmail(
            $donor,
            $otp,
            trim((string) ($donor->email ?? '')),
        );
    }

    /**
     * @return array{sent: bool, channel?: string, destination?: string, message: string}
     */
    private function deliverEmail(Donor $donor, string $otp, string $email): array
    {
        $email = $this->normalizeEmail($email);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent' => false,
                'message' => 'Unable to send a code to this email. Please try mobile sign-in, or continue as a guest.',
            ];
        }

        Mail::to($email)->send(new DonorOtpMail(
            otp: $otp,
            donorName: (string) ($donor->name ?: 'Donor'),
            ttlMinutes: (int) ceil(((int) config('donation.otp.ttl_seconds', 300)) / 60),
        ));

        return [
            'sent' => true,
            'channel' => 'email',
            'destination' => $this->maskEmail($email),
            'message' => 'We sent a code to '.$this->maskEmail($email).'.',
        ];
    }

    private function sendWhatsAppOtp(Donor $donor, string $otp): bool
    {
        return $this->aiSensyService->sendOtpCampaign(
            (string) $donor->phone,
            trim((string) ($donor->name ?: 'Donor')),
            $otp,
            $this->resolveOtpAccount(),
        );
    }

    private function resolveOtpAccount(): ?AisensyAccount
    {
        $configuredId = Setting::getValue(Setting::AISENSY_OTP_ACCOUNT_ID);

        if ($configuredId !== null && ctype_digit($configuredId)) {
            $account = AisensyAccount::query()
                ->whereKey((int) $configuredId)
                ->where('is_active', true)
                ->first();

            if ($account !== null) {
                return $account;
            }
        }

        return AisensyAccount::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    private function generateOtp(): string
    {
        $length = max(4, min(8, (int) config('donation.otp.length', 6)));
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function emailIdentityKey(string $email): string
    {
        return 'email:'.$this->normalizeEmail($email);
    }

    private function otpKey(string $identity): string
    {
        return 'donate_otp:'.$identity;
    }

    private function cooldownKey(string $identity): string
    {
        return 'donate_otp_cooldown:'.$identity;
    }

    private function dailySendKey(string $identity): string
    {
        return 'donate_otp_daily:'.$identity.':'.now()->toDateString();
    }

    private function cooldownRemainingSeconds(string $identity): int
    {
        $expiresAt = Cache::get($this->cooldownKey($identity));

        if (! is_numeric($expiresAt)) {
            return Cache::has($this->cooldownKey($identity))
                ? (int) config('donation.otp.resend_seconds', 60)
                : 0;
        }

        return max(0, (int) $expiresAt - now()->timestamp);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return 'your email';
        }

        $visible = substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }

    private function maskPhone(string $phone): string
    {
        $digits = $this->normalizePhone($phone);

        if (strlen($digits) < 4) {
            return 'your WhatsApp';
        }

        return '******'.substr($digits, -4);
    }
}
