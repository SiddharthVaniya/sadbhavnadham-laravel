<?php

namespace App\Support\Meta;

use App\Models\DonationOrder;
use App\Models\LinkTrackingVisit;
use App\Services\LinkTrackingService;
use Illuminate\Support\Str;

class MetaCapUserDataBuilder
{
    public function __construct(
        private readonly LinkTrackingService $linkTracking,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forOrder(DonationOrder $order, ?string $clientUserAgent = null): array
    {
        $visit = $this->linkTracking->resolveVisitForConversion($order);

        $fbclid = $this->resolveFbclid($order, $visit);
        $clickTime = $visit?->created_at?->getTimestamp()
            ?? $order->created_at?->getTimestamp()
            ?? now()->getTimestamp();

        $userData = array_filter([
            'em' => $this->hashEmail($order->donor_email),
            'ph' => $this->hashPhone($order->donor_phone, $order->donor_country_code),
            'fn' => $this->hashNamePart($this->firstName((string) $order->donor_name)),
            'ln' => $this->hashNamePart($this->lastName((string) $order->donor_name)),
            'ct' => $this->hashCity($order->city),
            'st' => $this->hashRegion($order->state),
            'zp' => $this->hashZip($order->pincode),
            'country' => $this->hashCountry($order->country ?: $order->donor_country_code),
            'client_ip_address' => $this->clientIp($order),
            'client_user_agent' => $clientUserAgent ?: $visit?->user_agent,
            'fbc' => $fbclid !== null ? $this->formatFbc($fbclid, $clickTime) : null,
        ], fn (mixed $value) => $value !== null && $value !== '');

        return $userData;
    }

    public function hashNormalized(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }

    private function hashEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return $this->hashNormalized(trim($email));
    }

    private function hashPhone(?string $phone, ?string $countryCode): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $cc = preg_replace('/\D+/', '', (string) $countryCode) ?? '';

        if ($cc !== '' && ! str_starts_with($digits, $cc)) {
            $digits = $cc.ltrim($digits, '0');
        }

        return $this->hashNormalized($digits);
    }

    private function hashNamePart(?string $part): ?string
    {
        return $this->hashNormalized($part);
    }

    private function hashCity(?string $city): ?string
    {
        return $this->hashNormalized(preg_replace('/[^a-z]/', '', strtolower((string) $city)) ?: null);
    }

    private function hashRegion(?string $region): ?string
    {
        return $this->hashNormalized(preg_replace('/[^a-z]/', '', strtolower((string) $region)) ?: null);
    }

    private function hashZip(?string $zip): ?string
    {
        $normalized = preg_replace('/\s+/', '', strtolower((string) $zip)) ?? '';

        return $normalized !== '' ? $this->hashNormalized($normalized) : null;
    }

    private function hashCountry(?string $country): ?string
    {
        $value = strtolower(trim((string) $country));

        if ($value === '') {
            return null;
        }

        if (strlen($value) === 2) {
            return $this->hashNormalized($value);
        }

        if (in_array($value, ['india', 'in'], true)) {
            return $this->hashNormalized('in');
        }

        return $this->hashNormalized(substr($value, 0, 2));
    }

    private function clientIp(DonationOrder $order): ?string
    {
        $ip = trim((string) $order->ip_address);

        return $ip !== '' ? $ip : null;
    }

    private function resolveFbclid(DonationOrder $order, ?LinkTrackingVisit $visit): ?string
    {
        if ($visit !== null && filled($visit->fbclid)) {
            return trim((string) $visit->fbclid);
        }

        $path = (string) ($order->landing_path ?? '');

        if ($path === '') {
            return null;
        }

        $query = parse_url($path, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            if (! str_contains($path, '?')) {
                return null;
            }

            $query = Str::after($path, '?');
        }

        parse_str($query, $params);

        $fbclid = $params['fbclid'] ?? null;

        return is_string($fbclid) && $fbclid !== '' ? $fbclid : null;
    }

    private function formatFbc(string $fbclid, int $clickTimestamp): string
    {
        return 'fb.1.'.$clickTimestamp.'.'.$fbclid;
    }

    private function firstName(string $name): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return trim(explode(' ', $name, 2)[0]);
    }

    private function lastName(string $name): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $parts = explode(' ', $name, 2);

        return isset($parts[1]) ? trim($parts[1]) : null;
    }
}
