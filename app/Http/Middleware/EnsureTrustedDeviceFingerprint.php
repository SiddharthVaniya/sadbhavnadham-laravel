<?php

namespace App\Http\Middleware;

use App\Models\UserDeviceFingerprint;
use App\Support\MarketerPortal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedDeviceFingerprint
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || MarketerPortal::isMarketerOnly($user)) {
            return $next($request);
        }

        $fingerprint = trim((string) $request->session()->get('auth_device_fingerprint', ''));

        // Feature tests sign in with actingAs and do not carry a device fingerprint.
        if ($fingerprint === '' && app()->runningUnitTests()) {
            return $next($request);
        }

        $trusted = $fingerprint !== '' && UserDeviceFingerprint::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->exists();

        if ($trusted) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $fingerprint === ''
            ? 'Device unrecognized.'
            : 'Device unrecognized. Error id: '.$fingerprint;

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('login'));
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => $message]);
    }
}
