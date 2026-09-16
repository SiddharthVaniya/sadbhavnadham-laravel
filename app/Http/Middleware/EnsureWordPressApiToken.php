<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWordPressApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('app.wp_api_token', ''));
        $provided = (string) $request->header('X-WP-TOKEN', '');

        if (! $this->isConfiguredToken($expected) || ! hash_equals($expected, $provided)) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }

    private function isConfiguredToken(string $token): bool
    {
        return $token !== '' && $token !== 'YOUR_WP_API_TOKEN_HERE';
    }
}
