<?php

namespace App\Support\Attribution;

use Illuminate\Support\Str;

class AttributionTaxonomy
{
    public const SOURCE_META = 'meta';

    public const SOURCE_GOOGLE = 'google';

    public const SOURCE_YOUTUBE = 'youtube';

    public const SOURCE_WHATSAPP = 'whatsapp';

    public const SOURCE_STAFF = 'staff';

    public const SOURCE_WORDPRESS = 'wordpress';

    public const SOURCE_EMAIL = 'email';

    public const SOURCE_ORGANIC = 'organic';

    public const SOURCE_UNKNOWN = 'unknown';

    public const MEDIUM_PAID_SOCIAL = 'paid_social';

    public const MEDIUM_PAID_SEARCH = 'paid_search';

    public const MEDIUM_PAID_VIDEO = 'paid_video';

    public const MEDIUM_ORGANIC_SOCIAL = 'organic_social';

    public const MEDIUM_REFERRAL = 'referral';

    public const MEDIUM_EMAIL = 'email';

    public const MEDIUM_WEBSITE = 'website';

    public const MEDIUM_MESSAGING = 'messaging';

    public const MEDIUM_NONE = 'none';

    public const PLATFORM_FACEBOOK = 'facebook';

    public const PLATFORM_INSTAGRAM = 'instagram';

    public const PLATFORM_MESSENGER = 'messenger';

    public const PLATFORM_AUDIENCE_NETWORK = 'audience_network';

    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_META => 'Meta',
            self::SOURCE_GOOGLE => 'Google',
            self::SOURCE_YOUTUBE => 'YouTube',
            self::SOURCE_WHATSAPP => 'WhatsApp',
            self::SOURCE_STAFF => 'Staff / referral',
            self::SOURCE_WORDPRESS => 'WordPress',
            self::SOURCE_EMAIL => 'Email',
            self::SOURCE_ORGANIC => 'Direct / organic',
            self::SOURCE_UNKNOWN => 'Unknown / other',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function platformOptions(): array
    {
        return [
            self::PLATFORM_FACEBOOK => 'Facebook',
            self::PLATFORM_INSTAGRAM => 'Instagram',
            self::PLATFORM_MESSENGER => 'Messenger',
            self::PLATFORM_AUDIENCE_NETWORK => 'Audience Network',
        ];
    }

    public static function sourceLabel(?string $source): string
    {
        if ($source === null || $source === '') {
            return 'Unknown';
        }

        return self::sourceOptions()[$source] ?? Str::headline($source);
    }

    public static function platformLabel(?string $platform): string
    {
        if ($platform === null || $platform === '') {
            return '';
        }

        return self::platformOptions()[$platform] ?? Str::headline(str_replace('_', ' ', $platform));
    }

    public static function mediumLabel(?string $medium): string
    {
        if ($medium === null || $medium === '') {
            return '';
        }

        return match ($medium) {
            self::MEDIUM_PAID_SOCIAL => 'Paid social',
            self::MEDIUM_PAID_SEARCH => 'Paid search',
            self::MEDIUM_PAID_VIDEO => 'Paid video',
            self::MEDIUM_ORGANIC_SOCIAL => 'Organic social',
            self::MEDIUM_REFERRAL => 'Referral',
            self::MEDIUM_EMAIL => 'Email',
            self::MEDIUM_WEBSITE => 'Website',
            self::MEDIUM_MESSAGING => 'Messaging',
            self::MEDIUM_NONE => 'None',
            default => Str::headline(str_replace('_', ' ', $medium)),
        };
    }

    /**
     * Human-friendly label for admin tables, e.g. "Meta · Instagram · Paid social".
     */
    public static function displayLabel(
        ?string $source,
        ?string $medium = null,
        ?string $platform = null,
    ): string {
        $parts = [];

        if (filled($source)) {
            $parts[] = self::sourceLabel($source);
        }

        if (filled($platform)) {
            $parts[] = self::platformLabel($platform);
        }

        if (filled($medium) && $medium !== self::MEDIUM_NONE) {
            $parts[] = self::mediumLabel($medium);
        }

        if ($parts === []) {
            return 'Unknown';
        }

        return implode(' · ', $parts);
    }

    /**
     * @return list<string>
     */
    public static function metaRawSources(): array
    {
        return ['meta', 'facebook', 'fb', 'instagram', 'ig'];
    }
}
