<?php

namespace App\Support;

class Branding
{
    public static function name(): string
    {
        return (string) config('branding.name', config('app.name', 'Sadbhavna'));
    }

    public static function shortName(): string
    {
        return (string) config('branding.short_name', 'Sadbhavna');
    }

    public static function legalName(): string
    {
        return (string) config('branding.legal_name', self::name());
    }

    public static function tagline(): string
    {
        return (string) config('branding.tagline', '');
    }

    public static function adminLabel(): string
    {
        return (string) config('branding.admin_label', 'Donation Admin');
    }

    public static function razorpayName(): string
    {
        return (string) config('branding.razorpay_name', self::shortName());
    }

    public static function recurringMandateText(): string
    {
        return self::interpolate((string) config('branding.recurring_mandate', ''));
    }

    public static function receiptThankYouText(): string
    {
        return self::interpolate((string) config('branding.receipt_thank_you', ''));
    }

    public static function paymentLinkDescription(): string
    {
        return self::interpolate((string) config('branding.payment_link_description', ''));
    }

    public static function url(string $key): ?string
    {
        $url = config("branding.urls.{$key}");

        return is_string($url) && $url !== '' ? $url : null;
    }

    public static function assetPath(string $key): string
    {
        return (string) config("branding.assets.{$key}", '');
    }

    public static function assetUrl(string $key): string
    {
        $path = self::assetPath($key);

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset($path);
    }

    public static function interpolate(string $text): string
    {
        return str_replace(
            ['{brand}', '{name}', '{legal_name}'],
            [self::shortName(), self::name(), self::legalName()],
            $text
        );
    }

    /**
     * @return array{account_name: string, account_number: string, ifsc: string, bank_name: string, branch: string, account_type: string, upi_id: string, note: string}
     */
    public static function bank(): array
    {
        return [
            'account_name' => (string) config('branding.bank.account_name', ''),
            'account_number' => (string) config('branding.bank.account_number', ''),
            'ifsc' => (string) config('branding.bank.ifsc', ''),
            'bank_name' => (string) config('branding.bank.bank_name', ''),
            'branch' => (string) config('branding.bank.branch', ''),
            'account_type' => (string) config('branding.bank.account_type', ''),
            'upi_id' => (string) config('branding.bank.upi_id', ''),
            'note' => (string) config('branding.bank.note', ''),
        ];
    }

    public static function hasBankDetails(): bool
    {
        $bank = self::bank();

        return $bank['account_number'] !== '' || $bank['ifsc'] !== '' || $bank['upi_id'] !== '';
    }

    /**
     * @return array{address: string, phone_primary: string, phone_secondary: string, email: string}
     */
    public static function contact(): array
    {
        return [
            'address' => (string) config('branding.contact.address', ''),
            'phone_primary' => (string) config('branding.contact.phone_primary', ''),
            'phone_secondary' => (string) config('branding.contact.phone_secondary', ''),
            'email' => (string) config('branding.contact.email', ''),
        ];
    }

    /**
     * @return array{facebook: ?string, instagram: ?string, youtube: ?string, whatsapp: ?string}
     */
    public static function social(): array
    {
        return [
            'facebook' => self::nullableString(config('branding.social.facebook')),
            'instagram' => self::nullableString(config('branding.social.instagram')),
            'youtube' => self::nullableString(config('branding.social.youtube')),
            'whatsapp' => self::nullableString(config('branding.social.whatsapp')),
        ];
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public static function footerLinks(string $group): array
    {
        $links = config("branding.footer.{$group}", []);

        if (! is_array($links)) {
            return [];
        }

        $resolved = [];

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($label === '' || $url === '') {
                continue;
            }

            if ($url === '/bank-details' || str_ends_with($url, '/bank-details')) {
                $url = route('donate.bank-details');
            } elseif (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://') && ! str_starts_with($url, 'mailto:')) {
                $url = url($url);
            }

            $resolved[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $resolved;
    }

    /**
     * @return list<array{label: string, url: string, children: list<array{label: string, url: string}>}>
     */
    public static function headerNav(): array
    {
        $items = config('branding.header_nav', []);

        if (! is_array($items)) {
            return [];
        }

        $resolved = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));

            if ($label === '' || $url === '') {
                continue;
            }

            $children = [];
            $rawChildren = $item['children'] ?? [];

            if (is_array($rawChildren)) {
                foreach ($rawChildren as $child) {
                    if (! is_array($child)) {
                        continue;
                    }

                    $childLabel = trim((string) ($child['label'] ?? ''));
                    $childUrl = trim((string) ($child['url'] ?? ''));

                    if ($childLabel === '' || $childUrl === '') {
                        continue;
                    }

                    $children[] = [
                        'label' => $childLabel,
                        'url' => $childUrl,
                    ];
                }
            }

            $resolved[] = [
                'label' => $label,
                'url' => $url,
                'children' => $children,
            ];
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'name' => self::name(),
            'shortName' => self::shortName(),
            'legalName' => self::legalName(),
            'tagline' => self::tagline(),
            'adminLabel' => self::adminLabel(),
            'razorpayName' => self::razorpayName(),
            'recurringMandateText' => self::recurringMandateText(),
            'logoUrl' => self::assetUrl('logo'),
            'logoPublicUrl' => self::assetUrl('logo_public'),
            'faviconUrl' => self::assetUrl('favicon'),
            'ogImageUrl' => self::assetUrl('og_image'),
            'upiQrUrl' => self::assetUrl('upi_qr'),
            'upiAppsUrl' => self::assetUrl('upi_apps'),
            'paymentGatewaysUrl' => self::assetUrl('payment_gateways'),
            'footerAbout' => (string) config('branding.footer.about', ''),
            'usefulLinks' => self::footerLinks('useful_links'),
            'initiativeLinks' => self::footerLinks('initiatives'),
            'headerNav' => self::headerNav(),
            'contact' => self::contact(),
            'social' => self::social(),
            'bank' => self::bank(),
            'hasBankDetails' => self::hasBankDetails(),
            'urls' => [
                'website' => self::url('website'),
                'privacy' => self::url('privacy'),
                'refund' => self::url('refund'),
                'terms' => self::url('terms'),
            ],
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
