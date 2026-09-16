<?php

namespace App\Http\Middleware;

use App\Support\BrandingStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyBrandingConfig
{
    public function handle(Request $request, Closure $next): Response
    {
        BrandingStore::applyToConfig();

        return $next($request);
    }
}
