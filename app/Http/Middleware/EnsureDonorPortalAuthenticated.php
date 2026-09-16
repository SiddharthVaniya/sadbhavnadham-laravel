<?php

namespace App\Http\Middleware;

use App\Support\DonorPortalSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDonorPortalAuthenticated
{
    public function __construct(private DonorPortalSession $portalSession) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->portalSession->donor() === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Please sign in to continue.',
                    'signed_in' => false,
                ], 401);
            }

            return redirect()
                ->route('donate.index', ['signin' => 1])
                ->with('status', 'Please sign in to view your donations.');
        }

        return $next($request);
    }
}
