<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstructorApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->role === 'instructor' && ! ($user->metadata['application_status'] ?? null === 'approved')) {
            abort(403, 'Instructor account is pending approval.');
        }
        return $next($request);
    }
}
