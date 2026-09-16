<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class DonorOtpRateLimiter
{
    public static function register(): void
    {
        RateLimiter::for('donor-otp-send', function (Request $request) {
            $identity = self::identityKey($request);

            return [
                Limit::perMinute(60)->by('otp-send-ip:'.$request->ip())->response(fn () => self::tooManyResponse()),
                Limit::perMinute(8)->by('otp-send-id:'.$identity)->response(fn () => self::tooManyResponse()),
            ];
        });

        RateLimiter::for('donor-otp-verify', function (Request $request) {
            $identity = self::identityKey($request);

            return [
                Limit::perMinute(60)->by('otp-verify-ip:'.$request->ip())->response(fn () => self::tooManyResponse()),
                Limit::perMinute(20)->by('otp-verify-id:'.$identity)->response(fn () => self::tooManyResponse()),
            ];
        });
    }

    private static function identityKey(Request $request): string
    {
        $method = strtolower((string) $request->input('login_method', 'phone'));

        if ($method === 'email') {
            $email = mb_strtolower(trim((string) $request->input('donor_email', '')));

            return $email !== '' ? 'email:'.$email : 'email:unknown';
        }

        $phone = preg_replace('/\D+/', '', (string) $request->input('donor_phone', '')) ?: 'unknown';

        return 'phone:'.$phone;
    }

    private static function tooManyResponse()
    {
        return response()->json([
            'message' => 'Too many requests. Please wait a minute and try again.',
            'retry_after' => 60,
        ], 429);
    }
}
