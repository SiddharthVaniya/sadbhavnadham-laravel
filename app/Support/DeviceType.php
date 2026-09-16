<?php

namespace App\Support;

class DeviceType
{
    public const Mobile = 'mobile';

    public const Tablet = 'tablet';

    public const Desktop = 'desktop';

    public const Unknown = 'unknown';

    public static function fromUserAgent(?string $userAgent): string
    {
        $userAgent = strtolower(trim((string) $userAgent));

        if ($userAgent === '' || self::isServerAgent($userAgent)) {
            return self::Unknown;
        }

        if (str_contains($userAgent, 'ipad') || str_contains($userAgent, 'tablet') || str_contains($userAgent, 'playbook') || str_contains($userAgent, 'kindle')) {
            return self::Tablet;
        }

        if (
            str_contains($userAgent, 'mobi')
            || str_contains($userAgent, 'iphone')
            || str_contains($userAgent, 'ipod')
            || str_contains($userAgent, 'android')
        ) {
            return self::Mobile;
        }

        return self::Desktop;
    }

    public static function normalize(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, [self::Mobile, self::Tablet, self::Desktop], true)
            ? $value
            : null;
    }

    private static function isServerAgent(string $userAgent): bool
    {
        return $userAgent === 'node'
            || str_starts_with($userAgent, 'node/')
            || str_contains($userAgent, 'undici')
            || str_contains($userAgent, 'next.js');
    }
}
