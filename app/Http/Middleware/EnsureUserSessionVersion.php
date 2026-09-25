<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserSessionVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $sessionVersion = $request->session()->get('auth_session_version');
        $userVersion = (int) ($user->session_version ?? 0);

        // Older sessions / actingAs() may not have the key yet — bind instead of forcing logout.
        if ($sessionVersion === null) {
            $request->session()->put('auth_session_version', $userVersion);

            return $next($request);
        }

        if ((int) $sessionVersion !== $userVersion) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->header('X-Inertia')) {
                return Inertia::location(route('login'));
            }

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'You were signed out of all devices. Please sign in again.',
                ]);
        }

        return $next($request);
    }
}
