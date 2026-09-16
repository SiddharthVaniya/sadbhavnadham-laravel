<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use App\Support\MarketerPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPortalAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'USER DOES NOT HAVE THE RIGHT ROLES.');
        }

        if (MarketerPortal::isMarketerOnly($user)) {
            return redirect()->route('marketer.dashboard');
        }

        if (AdminPermissions::userCanAny($user, AdminPermissions::portalPermissions())) {
            return $next($request);
        }

        abort(403, 'USER DOES NOT HAVE THE RIGHT ROLES.');
    }
}
