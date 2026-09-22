<?php

if (! function_exists('donation_env_hex_color')) {
    /**
     * Read a hex color from .env.
     *
     * Unquoted values like #840405 are treated as comments by dotenv, so use
     * DONATION_CERTIFICATE_NAME_COLOR="#840405" or 840405 (without #).
     */
    function donation_env_hex_color(string $key, string $default): string
    {
        $value = env($key);

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($value === '') {
            return $default;
        }

        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $value) === 1) {
            return '#'.ltrim($value, '#');
        }

        return $value;
    }
}
