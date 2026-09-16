<?php

namespace App\Http\Middleware;

use App\Support\MarketerPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketerPortalAccess
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

        if (MarketerPortal::isMarketer($user)) {
            return $next($request);
        }

        if (MarketerPortal::canAccessAdmin($user)) {
            return redirect()->route('admin.dashboard');
        }

        abort(403, 'USER DOES NOT HAVE THE RIGHT ROLES.');
    }
}
