<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($user = $request->user()) {
            \App\Models\ActivityLog::create([
                'user_id'    => $user->id,
                'tenant_id'  => currentTenant()?->id,
                'method'     => $request->method(),
                'path'       => $request->path(),
                'ip'         => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                'status'     => $response->getStatusCode(),
            ]);
        }
    }
}
