<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowWordPressEmbed
{
    /**
     * Allow the main WordPress site to embed public donation pages in an iframe.
     * Admin routes stay same-origin only.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('admin', 'admin/*')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");

            return $response;
        }

        // CSP frame-ancestors replaces X-Frame-Options for modern browsers.
        $response->headers->remove('X-Frame-Options');
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self' https://sadbhavnadham.org https://www.sadbhavnadham.org"
        );

        return $response;
    }
}
