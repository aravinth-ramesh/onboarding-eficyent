<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorization gate for the admin JSON API: the authenticated (Sanctum)
 * user must be flagged is_admin. Without this, any client token could reach
 * the admin CRUD endpoints — auth:sanctum only proves login, not role.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
