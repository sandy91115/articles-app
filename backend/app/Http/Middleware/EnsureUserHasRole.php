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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $allowedRoles = array_map(
            fn (string $role) => UserRole::from($role),
            $roles,
        );

        if (! $user->hasRole(...$allowedRoles)) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
