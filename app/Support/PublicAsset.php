<?php

namespace App\Support;

class PublicAsset
{
    public static function url(string $path): string
    {
        $relative = ltrim($path, '/');
        $fullPath = public_path($relative);
        $url = asset($relative);

        if (is_file($fullPath)) {
            return $url.'?v='.filemtime($fullPath);
        }

        return $url;
    }
}
