<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{User, Course, Enrollment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Log};
use Laravel\Sanctum\PersonalAccessToken; // Add this

class AdminController extends Controller
{
    /**
     * Get pending instructor applications
     */
    public function pendingInstructors()
    {
        Gate::authorize('admin', User::class);

        $instructors = User::where('role', 'instructor')
            ->whereJsonContains('metadata->application_status', 'pending')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $instructors]);
    }

    /**
     * Approve or reject instructor
     */
    public function approveInstructor(User $instructor, Request $request)
    {
        Gate::authorize('admin', User::class);

        $validated = $request->validate([
            'approve' => 'required|boolean',
            'rejection_reason' => 'required_if:approve,false|nullable|string',
        ]);

        $instructor->update([
            'is_verified' => $validated['approve'],
            'metadata' => array_merge($instructor->metadata, [
                'application_status' => $validated['approve'] ? 'approved' : 'rejected',
                'reviewed_at' => now()->toISOString(),
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => $validated['approve'] ? 'Instructor approved' : 'Instructor rejected',
        ]);
    }

    /**
     * Get pending courses for approval
     */
    public function pendingCourses()
    {
        Gate::authorize('admin', Course::class);

        $courses = Course::where('status', 'pending')
            ->with('instructor', 'category')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $courses]);
    }

    /**
     * Approve or reject course
     */
    public function approveCourse(Course $course, Request $request)
    {
        Gate::authorize('admin', $course);

        $validated = $request->validate([
            'approve' => 'required|boolean',
            'rejection_reason' => 'required_if:approve,false|nullable|string',
        ]);

        $course->update([
            'status' => $validated['approve'] ? 'approved' : 'rejected',
            'is_approved' => $validated['approve'],
            'is_published' => $validated['approve'],
            'metadata' => array_merge($course->metadata ?? [], [
                'reviewed_at' => now()->toISOString(),
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => $validated['approve'] ? 'Course approved' : 'Course rejected',
        ]);
    }

    /**
     * Get platform-wide analytics
     */
    public function platformOverview(Request $request)
    {
        Gate::authorize('admin', User::class);

        $overview = [
            'total_users' => User::count(),
            'total_instructors' => User::instructors()->count(),
            'total_courses' => Course::published()->count(),
            'total_enrollments' => Enrollment::count(),
            'total_revenue' => Enrollment::completed()->sum('paid_amount'),
            'monthly_growth' => $this->getMonthlyGrowth(),
        ];

        return response()->json(['success' => true, 'data' => $overview]);
    }

    protected function getMonthlyGrowth(): array
    {
        return [
            'users' => User::whereMonth('created_at', now()->month)->count(),
            'courses' => Course::whereMonth('created_at', now()->month)->count(),
            'enrollments' => Enrollment::whereMonth('enrolled_at', now()->month)->count(),
            'revenue' => Enrollment::completed()->whereMonth('enrolled_at', now()->month)->sum('paid_amount'),
        ];
    }
}
