<?php

namespace App\Http\Middleware;

use App\Support\AdminNavigation;
use App\Support\Branding;
use App\Support\MarketerNavigation;
use App\Support\MarketerPortal;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $isMarketerPortal = $request->routeIs('marketer.*');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'referral_code' => $user->referral_code,
                    'initials' => collect(explode(' ', (string) $user->name))
                        ->filter()
                        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
                        ->take(2)
                        ->implode(''),
                ] : null,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->values()->all() : [],
            ],
            'navigation' => $user
                ? ($isMarketerPortal || ($user && MarketerPortal::isMarketerOnly($user))
                    ? MarketerNavigation::build($user)
                    : AdminNavigation::build($user))
                : [],
            'portal' => [
                'home' => $isMarketerPortal ? '/marketer' : '/admin',
                'label' => $isMarketerPortal ? 'Marketer' : 'Admin',
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'tone' => fn () => $request->session()->get('flash_tone'),
            ],
            'csrf_token' => csrf_token(),
            'appName' => Branding::name(),
            'branding' => Branding::toArray(),
            'currentRoute' => $request->route()?->getName(),
        ];
    }
}
