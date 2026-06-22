<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Master users (tenant_id = null) bypass this check
        if ($user && $user->tenant_id === null) {
            return $next($request);
        }
        
        // Check if user's tenant is active
        if ($user && $user->tenant) {
            if ($user->tenant->status !== 'active') {
                abort(403, 'Your account has been suspended or is inactive.');
            }
        }
        
        return $next($request);
    }
}
