<?php

namespace App\Support;

use App\Models\Cause;
use Illuminate\Support\Str;

class Seo
{
    public static function metaDescription(?string $text, ?string $fallback = null): string
    {
        $fallback ??= Branding::tagline();
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($text ?: $fallback))) ?? '');

        return Str::limit($clean, 160, '');
    }

    public static function canonicalUrl(?string $url = null): string
    {
        if (is_string($url) && $url !== '') {
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }

            $configured = config('branding.seo.canonical_url');
            if (is_string($configured) && $configured !== '') {
                return rtrim($configured, '/').'/'.ltrim($url, '/');
            }

            return url($url);
        }

        $configured = config('branding.seo.canonical_url');
        $path = request()->getPathInfo();
        $query = request()->getQueryString();

        if (is_string($configured) && $configured !== '') {
            $base = rtrim($configured, '/');
            $canonical = $base.$path;

            return $query ? $canonical.'?'.$query : $canonical;
        }

        return url()->current();
    }

    public static function absoluteImageUrl(?string $path = null): string
    {
        if (is_string($path) && $path !== '') {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            return asset($path);
        }

        $ogImage = Branding::assetUrl('og_image');

        if ($ogImage !== '') {
            return $ogImage;
        }

        return Branding::assetUrl('logo_public');
    }

    public static function pageTitle(string $title): string
    {
        return trim($title);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    public static function jsonLdGraph(array ...$nodes): array
    {
        $nodes = array_values(array_filter($nodes));

        if (count($nodes) === 1) {
            return [
                '@context' => 'https://schema.org',
                ...$nodes[0],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $nodes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function jsonLdTag(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return '<script type="application/ld+json">'.$json.'</script>';
    }

    /**
     * @return array<string, mixed>
     */
    public static function organizationSchema(): array
    {
        $homeUrl = DonationPublicFrontend::homeUrl();

        return [
            '@type' => 'NGO',
            '@id' => $homeUrl.'#organization',
            'name' => Branding::name(),
            'legalName' => Branding::legalName(),
            'url' => $homeUrl,
            'logo' => self::absoluteImageUrl(),
            'description' => self::metaDescription(Branding::tagline()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function websiteSchema(): array
    {
        $homeUrl = DonationPublicFrontend::homeUrl();

        return [
            '@type' => 'WebSite',
            '@id' => $homeUrl.'#website',
            'url' => $homeUrl,
            'name' => Branding::name(),
            'description' => self::metaDescription(config('branding.seo.home_description')),
            'publisher' => ['@id' => $homeUrl.'#organization'],
        ];
    }

    /**
     * @param  iterable<int, Cause>|null  $causes
     * @return array<string, mixed>
     */
    public static function homePageSchemas(?iterable $causes = null): array
    {
        $homeUrl = DonationPublicFrontend::homeUrl();

        $nodes = [
            self::organizationSchema(),
            self::websiteSchema(),
            [
                '@type' => 'WebPage',
                '@id' => $homeUrl.'#webpage',
                'url' => $homeUrl,
                'name' => 'Donate | '.Branding::name(),
                'description' => self::metaDescription(config('branding.seo.home_description')),
                'isPartOf' => ['@id' => $homeUrl.'#website'],
                'about' => ['@id' => $homeUrl.'#organization'],
            ],
        ];

        if ($causes !== null) {
            $items = [];
            $position = 1;

            foreach ($causes as $cause) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'name' => $cause->title,
                    'url' => DonationPublicFrontend::donateCauseUrl($cause->slug),
                ];
                $position++;
            }

            if ($items !== []) {
                $nodes[] = [
                    '@type' => 'ItemList',
                    '@id' => $homeUrl.'#causes',
                    'name' => 'Donation causes',
                    'itemListElement' => $items,
                ];
            }
        }

        return self::jsonLdGraph(...$nodes);
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbSchema(array $crumbs): array
    {
        $items = [];

        foreach ($crumbs as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function causePageSchemas(Cause $cause, ?string $imagePath = null): array
    {
        $homeUrl = DonationPublicFrontend::homeUrl();
        $causeUrl = DonationPublicFrontend::donateCauseUrl($cause->slug);
        $summary = self::metaDescription($cause->excerpt ?: $cause->description);

        return self::jsonLdGraph(
            self::organizationSchema(),
            self::websiteSchema(),
            self::breadcrumbSchema([
                ['name' => 'All Causes', 'url' => $homeUrl],
                ['name' => $cause->title, 'url' => $causeUrl],
            ]),
            [
                '@type' => 'WebPage',
                '@id' => $causeUrl.'#webpage',
                'url' => $causeUrl,
                'name' => $cause->title,
                'description' => $summary,
                'isPartOf' => ['@id' => $homeUrl.'#website'],
                'about' => ['@id' => $homeUrl.'#organization'],
                'primaryImageOfPage' => self::absoluteImageUrl($imagePath),
            ],
            [
                '@type' => 'DonateAction',
                '@id' => $causeUrl.'#donate',
                'name' => 'Donate to '.$cause->title,
                'target' => $causeUrl,
                'recipient' => ['@id' => $homeUrl.'#organization'],
            ],
        );
    }
}
