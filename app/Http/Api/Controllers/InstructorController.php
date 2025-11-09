<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\{UserResource, CourseResource};
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Cache, Log};

class InstructorController extends Controller
{
    protected $cacheTtl = 600; // 10 minutes

    /**
     * List all verified instructors
     */
    public function index(Request $request)
    {
        $instructors = Cache::remember('instructors_list_' . $request->get('page', 1), $this->cacheTtl, function () {
            return User::instructors()
                ->active()
                ->verified()
                ->withCount(['courses as published_courses_count' => fn($q) => $q->published()])
                ->withCount(['enrollments as total_students'])
                ->latest()
                ->paginate($request->get('per_page', 15));
        });

        return UserResource::collection($instructors);
    }

    /**
     * Get instructor profile with courses
     */
    public function show(User $instructor)
    {
        Gate::authorize('view', $instructor);

        $instructor->load([
            'courses' => fn($q) => $q->published()->withCount('enrollments'),
        ]);

        return response()->json([
            'success' => true,
            'instructor' => new UserResource($instructor),
        ]);
    }

    /**
     * Get instructor's courses (for public profile)
     */
    public function courses(User $instructor, Request $request)
    {
        Gate::authorize('view', $instructor);

        $courses = $instructor->courses()
            ->published()
            ->with('category')
            ->paginate($request->get('per_page', 15));

        return CourseResource::collection($courses);
    }

    /**
     * Apply to become an instructor
     */
    public function becomeInstructor(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'bio' => 'required|string|min:100|max:2000',
            'expertise' => 'required|array|min:1',
            'expertise.*' => 'string',
            'qualifications' => 'nullable|array',
            'qualifications.*' => 'string',
            'social_links' => 'nullable|array',
            'social_links.linkedin' => 'nullable|url',
            'social_links.twitter' => 'nullable|url',
        ]);

        $user = $request->user();

        if ($user->role === 'instructor' && $user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a verified instructor',
            ], 400);
        }

        $user->update([
            'role' => 'instructor',
            'phone' => $validated['phone'],
            'metadata' => array_merge($user->metadata ?? [], [
                'bio' => $validated['bio'],
                'expertise' => $validated['expertise'],
                'qualifications' => $validated['qualifications'] ?? [],
                'social_links' => $validated['social_links'] ?? [],
                'application_status' => 'pending',
                'applied_at' => now()->toISOString(),
            ]),
            'is_verified' => false,
        ]);

        $user->assignRole('instructor');

        // TODO: Send notification to admin

        Log::info('Instructor application submitted', ['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'message' => 'Your application has been submitted for review',
        ], 201);
    }
}
