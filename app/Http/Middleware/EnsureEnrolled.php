<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user  = $request->user();
        $course = $request->route('course');   // route-model binding

        if (! $user || ! $course) {
            abort(403);
        }

        $enrolled = $user->enrollments()
                         ->where('course_id', $course->id)
                         ->where('payment_status', 'completed')
                         ->exists();

        if (! $enrolled) {
            abort(403, 'You are not enrolled in this course.');
        }

        return $next($request);
    }
}
