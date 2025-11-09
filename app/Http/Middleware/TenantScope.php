<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantScope
{
    public function handle(Request $request, Closure $next)
    {
        // Example: resolve tenant from subdomain / header / authenticated user
        $tenant = currentTenant();               // helper you already have
        if ($tenant) {
            \App\Models\Tenant::scopeEveryQueryTo($tenant->id);
        }

        return $next($request);
    }
}
