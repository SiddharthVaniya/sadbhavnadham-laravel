<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class DonateCheckoutRateLimiter
{
    public static function register(): void
    {
        RateLimiter::for('donate-checkout', function (Request $request) {
            return [
                Limit::perMinute(30)->by('donate-checkout-donor:'.self::donorKey($request)),
                Limit::perMinute(300)->by('donate-checkout-ip:'.$request->ip()),
            ];
        });
    }

    private static function donorKey(Request $request): string
    {
        $email = mb_strtolower(trim((string) $request->input('donor_email', '')));
        $phone = preg_replace('/\D+/', '', (string) $request->input('donor_phone', '')) ?: '';

        if ($email === '' && $phone === '') {
            return 'anonymous:'.$request->ip();
        }

        return 'email:'.($email !== '' ? $email : 'unknown').'|phone:'.($phone !== '' ? $phone : 'unknown');
    }
}
