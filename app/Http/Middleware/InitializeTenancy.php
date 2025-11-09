<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use Illuminate\Support\Facades\{Cache, Log};

class InitializeTenancy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip tenancy for central domains
        if ($this->isCentralDomain($request)) {
            return $next($request);
        }

        // Initialize tenancy
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return $this->handleTenantNotFound($request);
        }

        // Check tenant status
        if (!$tenant->is_active) {
            return $this->handleInactiveTenant($tenant);
        }

        // Check subscription
        if ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isPast()) {
            return $this->handleExpiredSubscription($tenant);
        }

        // Initialize tenant context
        tenancy()->initialize($tenant);

        // Add tenant info to response headers
        return $next($request)->withHeaders([
            'X-Tenant-ID' => $tenant->id,
            'X-Tenant-Name' => $tenant->name,
        ]);
    }

    /**
     * Check if request is for central domain
     */
    protected function isCentralDomain(Request $request): bool
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        return in_array($host, $centralDomains);
    }

    /**
     * Resolve tenant from request
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // Try to resolve from domain
        $tenant = $this->resolveTenantByDomain($request);

        if (!$tenant) {
            // Try to resolve from subdomain
            $tenant = $this->resolveTenantBySubdomain($request);
        }

        if (!$tenant) {
            // Try to resolve from header (for API)
            $tenant = $this->resolveTenantByHeader($request);
        }

        return $tenant;
    }

    /**
     * Resolve tenant by domain
     */
    protected function resolveTenantByDomain(Request $request): ?Tenant
    {
        $domain = $request->getHost();

        return Cache::remember(
            "tenant_domain_{$domain}",
            3600,
            fn() => Tenant::whereHas('domains', function ($query) use ($domain) {
                $query->where('domain', $domain);
            })->first()
        );
    }

    /**
     * Resolve tenant by subdomain
     */
    protected function resolveTenantBySubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0] ?? null;

        if (!$subdomain || in_array($subdomain, ['www', 'api', 'central'])) {
            return null;
        }

        return Cache::remember(
            "tenant_subdomain_{$subdomain}",
            3600,
            fn() => Tenant::where('id', $subdomain)->first()
        );
    }

    /**
     * Resolve tenant by header (for API requests)
     */
    protected function resolveTenantByHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return null;
        }

        return Cache::remember(
            "tenant_{$tenantId}",
            3600,
            fn() => Tenant::find($tenantId)
        );
    }

    /**
     * Handle tenant not found
     */
    protected function handleTenantNotFound(Request $request): Response
    {
        Log::warning('Tenant not found', [
            'host' => $request->getHost(),
            'ip' => $request->ip(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Academy not found. Please check your URL.',
                'error_code' => 'TENANT_NOT_FOUND',
            ], 404);
        }

        return response()->view('errors.tenant-not-found', [], 404);
    }

    /**
     * Handle inactive tenant
     */
    protected function handleInactiveTenant(Tenant $tenant): Response
    {
        Log::info('Inactive tenant access attempt', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'This academy is currently inactive. Please contact support.',
            'error_code' => 'TENANT_INACTIVE',
            'support_email' => $tenant->email,
        ], 403);
    }

    /**
     * Handle expired subscription
     */
    protected function handleExpiredSubscription(Tenant $tenant): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Subscription has expired. Please renew to continue.',
            'error_code' => 'SUBSCRIPTION_EXPIRED',
            'expired_at' => $tenant->subscription_ends_at->toISOString(),
            'support_email' => $tenant->email,
        ], 402);
    }
}
