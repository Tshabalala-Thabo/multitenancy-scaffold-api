<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Exceptions\NoCurrentTenant;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantExists
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // This will throw an exception if no tenant is found
            tenant();
            
            return $next($request);
        } catch (NoCurrentTenant $e) {
            // No tenant found, redirect to central domain or show error
            return response()->json([
                'message' => 'Tenant not found. Please access through a valid tenant domain.'
            ], 404);
        }
    }
}