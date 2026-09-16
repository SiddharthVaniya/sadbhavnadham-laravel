<?php

use App\Models\Cause;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Live traffic is usually behind Cloudflare/nginx; trust forwarded HTTPS
        // so session cookies and CSRF stay consistent with the public URL.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\ApplyBrandingConfig::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\AllowWordPressEmbed::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'admin.portal' => \App\Http\Middleware\EnsureAdminPortalAccess::class,
            'marketer.portal' => \App\Http\Middleware\EnsureMarketerPortalAccess::class,
            'donor.portal' => \App\Http\Middleware\EnsureDonorPortalAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('admin*') || $request->is('marketer*') || $request->expectsJson()) {
                return null;
            }

            return response()->view('errors.404', [
                'featuredCauses' => Cause::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->limit(6)
                    ->get(['slug', 'title', 'excerpt']),
            ], 404);
        });
    })->create();
