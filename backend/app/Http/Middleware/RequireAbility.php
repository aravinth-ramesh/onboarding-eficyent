<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on one of the admin abilities (App\Enums\Ability). Usage:
 *   ->middleware('ability:' . Ability::MANAGE_USERS)
 * Runs after AdminAuth, so an admin is always present. Aborts 403 otherwise.
 */
class RequireAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        abort_unless(Auth::guard('admin')->user()?->hasAbility($ability), 403);

        return $next($request);
    }
}
