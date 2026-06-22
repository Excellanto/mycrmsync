<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Guard;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same as Spatie's permission middleware, but allows platform-scoped users (Super Admin / isMaster)
 * without relying on Gate registration order.
 */
class EnsurePermissionOrMaster
{
    public function handle(Request $request, Closure $next, string $permission, ?string $guard = null): Response
    {
        $authGuard = Auth::guard($guard);

        $user = $authGuard->user();

        if (! $user && $request->bearerToken() && config('permission.use_passport_client_credentials')) {
            $user = Guard::getPassportClient($guard);
        }

        if (! $user) {
            throw UnauthorizedException::notLoggedIn();
        }

        if ($user instanceof User && ($user->isMaster() || $user->hasRole('Super Admin'))) {
            return $next($request);
        }

        return app(PermissionMiddleware::class)->handle($request, $next, $permission, $guard);
    }
}
