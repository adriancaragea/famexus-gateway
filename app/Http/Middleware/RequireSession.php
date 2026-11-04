<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The sign-in endpoints log the user into a cookie session, which Sanctum only
 * starts for requests from SANCTUM_STATEFUL_DOMAINS. Checking first stops a
 * signup from creating an account and then failing.
 */
class RequireSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return response()->json([
                'message' => 'This request did not come from the Famexus app, so no session was started. Check that the app\'s host is listed in SANCTUM_STATEFUL_DOMAINS.',
            ], 400);
        }

        return $next($request);
    }
}
