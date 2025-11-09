<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\{CourseResource, CourseSectionResource};
use App\Http\Requests\StoreCourseRequest;
use App\Models\{Course, Category};
use App\Services\CourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Cache, Log};
use Laravel\Sanctum\PersonalAccessToken; // Add this

class CourseController extends Controller
{
    public function __construct(protected CourseService $courseService) {}

    /**
     * List courses with filters and pagination
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|exists:categories,id',
            'level' => 'nullable|in:beginner,intermediate,advanced,all',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'instructor' => 'nullable|exists:users,id',
            'rating' => 'nullable|numeric|min:0|max:5',
            'sort_by' => 'nullable|in:popularity,rating,price,duration,created_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $courses = $this->courseService->search($filters)
            ->paginate($filters['per_page'] ?? 15);

        return CourseResource::collection($courses);
    }

    /**
     * Get single course details
     */
    public function show(Course $course)
    {
        if (!$course->is_published) {
            $this->authorize('view', $course);
        }

        return response()->json([
            'success' => true,
            'course' => new CourseResource(
                $course->load(['instructor', 'category', 'sections.lessons'])
            ),
        ]);
    }

    /**
     * Create new course
     */
    public function store(StoreCourseRequest $request)
    {
        $this->authorize('create', Course::class);

        $course = $this->courseService->createCourse($request->validated(), $request->user());

        Log::info('Course created', [
            'course_id' => $course->id,
            'instructor_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully',
            'course' => new CourseResource($course),
        ], 201);
    }

    /**
     * Update course
     */
    public function update(StoreCourseRequest $request, Course $course)
    {
        $this->authorize('update', $course);

        $updatedCourse = $this->courseService->updateCourse($course, $request->validated());

        Log::info('Course updated', [
            'course_id' => $course->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully',
            'course' => new CourseResource($updatedCourse),
        ]);
    }

    /**
     * Delete course
     */
    public function destroy(Course $course)
    {
        $this->authorize('delete', $course);

        $this->courseService->deleteCourse($course);

        Log::info('Course deleted', [
            'course_id' => $course->id,
            'user_id' => request()->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully',
        ]);
    }

    /**
     * Get course content (sections & lessons)
     */
    public function content(Course $course)
    {
        if (!$course->is_published) {
            $this->authorize('view', $course);
        }

        $enrollment = auth()->user()?->enrollments()
            ->where('course_id', $course->id)
            ->where('payment_status', 'completed')
            ->first();

        $sections = $course->sections()
            ->with(['lessons' => function($query) use ($enrollment) {
                if (!$enrollment) {
                    $query->where('is_preview', true);
                }
            }])
            ->visible()
            ->orderBy('order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'sections' => CourseSectionResource::collection($sections),
                'is_enrolled' => (bool) $enrollment,
                'progress' => $enrollment?->progress ?? 0,
            ],
        ]);
    }

    /**
     * Get course analytics for instructor
     */
    public function analytics(Course $course)
    {
        $this->authorize('viewAnalytics', $course);

        return response()->json([
            'success' => true,
            'data' => $this->courseService->getAnalytics($course),
        ]);
    }
}
