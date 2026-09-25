<?php

namespace App\Providers;

use App\Models\Cause;
use App\Support\Branding;
use App\Support\BrandingStore;
use App\Support\DonateCheckoutRateLimiter;
use App\Support\DonorOtpRateLimiter;
use App\Support\DonorPortalSession;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\GeoIp\GeoIpLookupService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::useBuildDirectory('vite');

        if (! $this->app->runningInConsole()) {
            $rootUrl = $this->publicRootUrl(request());
            URL::forceRootUrl($rootUrl);

            if (str_starts_with($rootUrl, 'https://')) {
                URL::forceScheme('https');
            }
        } elseif (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        DonateCheckoutRateLimiter::register();
        DonorOtpRateLimiter::register();

        View::composer('*', function ($view): void {
            BrandingStore::applyToConfig();
            $view->with('branding', Branding::toArray());
        });

        // Only public donate pages need the OTP session; avoid touching session on admin/mail views.
        View::composer(['layouts.donate', 'partials.site-header', 'partials.donor-login-modal'], function ($view): void {
            $view->with('donorPortal', app(DonorPortalSession::class)->toFrontend());
        });

        Paginator::useBootstrapFive();

        Route::bind('cause', function (string $value): Cause {
            if (request()->is('admin/*')) {
                return Cause::query()
                    ->where(is_numeric($value) ? 'id' : 'slug', is_numeric($value) ? (int) $value : $value)
                    ->firstOrFail();
            }

            return Cause::query()->where('slug', $value)->firstOrFail();
        });

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }

    private function publicRootUrl(Request $request): string
    {
        $configured = rtrim((string) config('app.url'), '/');
        $configuredHost = parse_url($configured, PHP_URL_HOST);
        $requestHost = $request->getHost();
        $localHosts = ['localhost', '127.0.0.1'];

        if (
            is_string($requestHost)
            && $requestHost !== ''
            && in_array($configuredHost, $localHosts, true)
            && ! in_array($requestHost, $localHosts, true)
        ) {
            $scheme = ($request->header('X-Forwarded-Proto') === 'https' || $request->secure())
                ? 'https'
                : $request->getScheme();

            return $scheme.'://'.$requestHost;
        }

        return $configured;
    }
}
