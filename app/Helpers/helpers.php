<?php

use Illuminate\Support\Facades\Cache;
use App\Models\Tenant;

if (!function_exists('tenant')) {
    /**
     * Get current tenant instance
     */
    function tenant(): ?Tenant
    {
        return tenancy()->tenant;
    }
}

if (!function_exists('currentTenant')) {
    /**
     * Alias for tenant() function
     */
    function currentTenant(): ?Tenant
    {
        return tenant();
    }
}

if (!function_exists('format_duration')) {
    /**
     * Format duration in seconds to human-readable format
     */
    function format_duration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        }

        return sprintf('%ds', $secs);
    }
}

if (!function_exists('format_price')) {
    /**
     * Format price with currency
     */
    function format_price(float $price, string $currency = 'EGP'): string
    {
        return number_format($price, 2) . ' ' . $currency;
    }
}

if (!function_exists('sanitize_slug')) {
    /**
     * Sanitize string for URL slug
     */
    function sanitize_slug(string $text): string
    {
        return \Illuminate\Support\Str::slug($text);
    }
}

if (!function_exists('cache_remember_tenant')) {
    /**
     * Cache data with tenant-specific key
     */
    function cache_remember_tenant(string $key, int $ttl, \Closure $callback)
    {
        $tenantId = tenant()?->id ?? 'central';
        $fullKey = "tenant_{$tenantId}_{$key}";

        return Cache::remember($fullKey, $ttl, $callback);
    }
}

if (!function_exists('cache_forget_tenant')) {
    /**
     * Forget tenant-specific cache
     */
    function cache_forget_tenant(string $key): bool
    {
        $tenantId = tenant()?->id ?? 'central';
        $fullKey = "tenant_{$tenantId}_{$key}";

        return Cache::forget($fullKey);
    }
}

if (!function_exists('generate_reference')) {
    /**
     * Generate unique reference number
     */
    function generate_reference(string $prefix = 'REF'): string
    {
        return $prefix . '_' . time() . '_' . uniqid();
    }
}

if (!function_exists('log_activity')) {
    /**
     * Log user activity
     */
    function log_activity(string $action, ?string $description = null, ?array $metadata = null): void
    {
        if ($user = auth()->user()) {
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'tenant_id' => tenant()?->id,
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }
}

if (!function_exists('tenant_asset')) {
    /**
     * Get tenant-specific asset URL
     */
    function tenant_asset(string $path): string
    {
        $tenantId = tenant()?->id ?? 'central';
        return asset("storage/tenants/{$tenantId}/{$path}");
    }
}

if (!function_exists('is_enrolled')) {
    /**
     * Check if user is enrolled in course
     */
    function is_enrolled(\App\Models\Course $course, ?\App\Models\User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        return $user->enrollments()
            ->where('course_id', $course->id)
            ->where('payment_status', 'completed')
            ->exists();
    }
}

if (!function_exists('calculate_discount')) {
    /**
     * Calculate discount amount
     */
    function calculate_discount(float $price, float $discount, string $type = 'percentage'): float
    {
        if ($type === 'percentage') {
            return ($price * $discount) / 100;
        }

        return min($discount, $price);
    }
}

if (!function_exists('get_payment_gateway')) {
    /**
     * Get configured payment gateway
     */
    function get_payment_gateway(): string
    {
        return config('payment.default_gateway', 'fawry');
    }
}

if (!function_exists('is_production')) {
    /**
     * Check if app is in production
     */
    function is_production(): bool
    {
        return app()->environment('production');
    }
}

if (!function_exists('success_response')) {
    /**
     * Return success JSON response
     */
    function success_response($data = null, string $message = 'Success', int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}

if (!function_exists('error_response')) {
    /**
     * Return error JSON response
     */
    function error_response(string $message, int $status = 400, ?string $code = null): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code ?? 'ERROR',
                'status' => $status,
            ],
        ], $status);
    }
}

if (!function_exists('validate_egyptian_phone')) {
    /**
     * Validate Egyptian phone number
     */
    function validate_egyptian_phone(string $phone): bool
    {
        return preg_match('/^01[0125][0-9]{8}$/', $phone) === 1;
    }
}

if (!function_exists('mask_email')) {
    /**
     * Mask email for privacy
     */
    function mask_email(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        $maskedName = substr($name, 0, 2) . str_repeat('*', strlen($name) - 2);

        return $maskedName . '@' . $domain;
    }
}

if (!function_exists('generate_otp')) {
    /**
     * Generate OTP code
     */
    function generate_otp(int $length = 6): string
    {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('time_ago')) {
    /**
     * Get human-readable time difference
     */
    function time_ago(\DateTime|string $datetime): string
    {
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }

        return $datetime->diff(new \DateTime())->format('%a days ago');
    }
}
