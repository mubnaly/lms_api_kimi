<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip in local/testing environments
        if (app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        // Get current tenant
        $tenant = tenant();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        // Check if tenant is active
        if (!$tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This academy is currently inactive. Please contact support.',
            ], 403);
        }

        // Check subscription expiry
        if ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription has expired. Please renew to continue.',
                'subscription_ends_at' => $tenant->subscription_ends_at->toISOString(),
            ], 402); // 402 Payment Required
        }

        // Add warning header if subscription is about to expire
        if ($tenant->subscription_ends_at && $tenant->subscription_ends_at->diffInDays(now()) <= 7) {
            $response = $next($request);
            $response->headers->set('X-Subscription-Warning', 'Subscription expires soon');
            $response->headers->set('X-Subscription-Expires', $tenant->subscription_ends_at->toISOString());
            return $response;
        }

        return $next($request);
    }
}
