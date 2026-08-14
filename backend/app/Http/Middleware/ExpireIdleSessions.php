<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * End a session that has sat idle past the configured window.
 *
 * API tokens were issued without an expiry, so a client left signed in
 * overnight was still signed in the next day and could resume the application
 * without re-authenticating (retest item 38).
 *
 * This must run BEFORE the sanctum guard: the guard stamps `last_used_at` to
 * now() as part of authenticating, so anything downstream of it always sees a
 * fresh timestamp and could never tell an idle session from an active one.
 */
class ExpireIdleSessions
{
    public function handle(Request $request, Closure $next): Response
    {
        $minutes = (int) config('sanctum.idle_timeout_minutes', 30);
        $bearer = $request->bearerToken();

        if ($minutes <= 0 || $bearer === null) {
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($bearer);

        // A token that has never been used is newly issued, so its creation
        // time is what the window runs from.
        $lastActive = $token?->last_used_at ?? $token?->created_at;

        if ($token && $lastActive && $lastActive->lt(now()->subMinutes($minutes))) {
            $token->delete();

            return response()->json([
                'message' => 'Your session expired after a period of inactivity. Please sign in again.',
                'code' => 'session_expired',
            ], 401);
        }

        return $next($request);
    }
}
