<?php

namespace App\Support;

class PublicMediaUrl
{
    public static function origin(): string
    {
        $configured = config('branding.media_origin');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    public static function fromStoredPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);

            if (! is_string($parsedPath) || $parsedPath === '') {
                return $path;
            }

            if (! self::isLocalMediaPath($parsedPath)) {
                return $path;
            }

            $path = $parsedPath;
        }

        $path = '/'.ltrim($path, '/');

        if (str_starts_with($path, '/donate-images/')) {
            $path = substr($path, strlen('/donate-images'));
        }

        return self::origin().$path;
    }

    /**
     * @return list<string>
     */
    public static function many(mixed $paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        $urls = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $url = self::fromStoredPath($path);

            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private static function isLocalMediaPath(string $path): bool
    {
        return str_starts_with($path, '/storage/')
            || str_starts_with($path, '/images/')
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/donate-images/');
    }
}
