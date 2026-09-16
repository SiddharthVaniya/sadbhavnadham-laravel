<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureThankYouAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasValidWordpressToken($request) || $request->hasValidSignature()) {
            return $next($request);
        }

        return new JsonResponse(['error' => 'Forbidden'], 403);
    }

    private function hasValidWordpressToken(Request $request): bool
    {
        $expected = trim((string) config('app.wp_api_token', ''));
        $provided = (string) $request->header('X-WP-TOKEN', '');

        return $expected !== ''
            && $expected !== 'YOUR_WP_API_TOKEN_HERE'
            && hash_equals($expected, $provided);
    }
}
