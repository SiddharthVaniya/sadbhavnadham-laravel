<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DonationPublicFrontend
{
    public static function baseUrl(): string
    {
        $configured = trim((string) config('donation.public_frontend_url', ''));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $website = Branding::url('website');

        if (is_string($website) && $website !== '') {
            return rtrim($website, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    public static function redirectsEnabled(): bool
    {
        return (bool) config('donation.redirect_public_donate_to_frontend', false);
    }

    public static function shouldRedirect(Request $request): bool
    {
        if (! self::redirectsEnabled()) {
            return false;
        }

        $frontendHost = parse_url(self::baseUrl(), PHP_URL_HOST);
        $currentHost = $request->getHost();

        if (! is_string($frontendHost) || $frontendHost === '' || $currentHost === '') {
            return false;
        }

        return strcasecmp($frontendHost, $currentHost) !== 0;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function absolute(string $path, array $query = []): string
    {
        $path = '/'.ltrim($path, '/');

        if ($path === '//') {
            $path = '/';
        }

        $url = self::baseUrl().($path === '/' ? '/' : $path);

        $pairs = [];

        foreach ($query as $key => $value) {
            if (! is_string($key) || $key === '' || $value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                $pairs[$key] = (string) $value;
            }
        }

        if ($pairs !== []) {
            $url .= '?'.http_build_query($pairs);
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function donateCauseUrl(string $slug, array $query = []): string
    {
        return self::absolute('/donate/'.$slug, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function homeUrl(array $query = []): string
    {
        return self::absolute('/', $query);
    }

    public static function redirectPreserveQuery(Request $request, string $path): ?RedirectResponse
    {
        if (! self::shouldRedirect($request)) {
            return null;
        }

        return redirect()->away(self::absolute($path, $request->query()), 301);
    }
}
