<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantCanUseFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = currentTenant();        // your helper
        if (! $tenant || ! $tenant->plan?->hasFeature($feature)) {
            abort(403, 'Feature not available on your current plan.');
        }
        return $next($request);
    }
}
