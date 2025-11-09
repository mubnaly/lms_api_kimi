<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * Global middleware that runs on every request.
     */
    protected $middleware = [
        // Laravel defaults
        \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,

        // Custom global middleware
        \App\Http\Middleware\TenantScope::class,
        \App\Http\Middleware\EnsureUserIsActive::class,
        \App\Http\Middleware\LogUserActivity::class,
    ];

    /**
     * Middleware groups applied to route groups.
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\AcceptJson::class,
        ],
    ];

    /**
     * Route-middleware aliases (short names).
     */
    protected $middlewareAliases = [
        'auth'             => \App\Http\Middleware\Authenticate::class,
        'auth.basic'       => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session'     => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers'    => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can'              => \Illuminate\Auth\Middleware\Authorize::class,
        'guest'            => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive'     => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed'           => \App\Http\Middleware\ValidateSignature::class,
        'throttle'         => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'         => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        /* ----------  custom aliases  ---------- */
        'verified-user'     => \App\Http\Middleware\EnsureUserIsVerified::class,
        'active'            => \App\Http\Middleware\EnsureUserIsActive::class,
        'approved-instructor'=> \App\Http\Middleware\EnsureInstructorApproved::class,
        'enrolled'          => \App\Http\Middleware\EnsureEnrolled::class,
        'accept-json'       => \App\Http\Middleware\AcceptJson::class,
        'feature'           => \App\Http\Middleware\TenantCanUseFeature::class,
        'role'              => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission'=> \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    ];
}
