<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->role;

        if (! $userRole instanceof UserRole) {
            abort(403, 'Unauthorized.');
        }

        $allowedRoles = array_map(
            fn (string $role) => UserRole::tryFrom($role),
            $roles,
        );

        if (! in_array($userRole, $allowedRoles, true)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
