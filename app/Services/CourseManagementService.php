<?php

namespace App\Services;

use App\Models\{Course, User};
use App\Http\Requests\StoreCourseRequest;
use Illuminate\Support\Facades\{DB, Storage, Cache};

class CourseManagementService
{
    public function __construct(
        private VideoService $videoService
    ) {}

    /**
     * Create a new course with all related media
     */
    public function create(User $instructor, StoreCourseRequest $request): Course
    {
        return DB::transaction(function () use ($instructor, $request) {
            $course = $instructor->courses()->create([
                ...$request->validated(),
                'status' => 'draft',
                'is_published' => false,
                'is_approved' => false,
                'students_count' => 0,
                'rating' => 0.00,
                'reviews_count' => 0,
            ]);

            $this->processCourseMedia($course, $request);

            return $course->fresh(['media']);
        });
    }

    /**
     * Update existing course
     */
    public function update(Course $course, StoreCourseRequest $request): Course
    {
        return DB::transaction(function () use ($course, $request) {
            $course->update($request->validated());

            $this->processCourseMedia($course, $request);

            $this->clearCourseCache($course);

            return $course->fresh(['media']);
        });
    }

    /**
     * Delete course with cleanup
     */
    public function delete(Course $course): bool
    {
        return DB::transaction(function () use ($course) {
            $course->enrollments()->delete();
            $course->reviews()->delete();
            $course->coupons()->delete();
            $course->wishlistUsers()->detach();

            $course->clearMediaCollection('thumbnail');

            return $course->delete();
        });
    }

    /**
     * Get paginated courses with filters
     */
    public function getFiltered(array $filters)
    {
        $cacheKey = $this->buildCacheKey($filters);

        return Cache::remember($cacheKey, 300, function () use ($filters) {
            return $this->buildQuery($filters)->paginate($filters['per_page'] ?? 15);
        });
    }

    /**
     * Get single course (with enrollment status for authenticated users)
     */
    public function getCourseDetail(Course $course, ?User $user): Course
    {
        return $course->load([
            'instructor',
            'category',
            'sections.lessons' => function ($query) use ($user) {
                if (!$user) {
                    $query->where('is_preview', true);
                }
            },
        ]);
    }

    /**
     * Get course content (for enrolled users or preview)
     */
    public function getCourseContent(Course $course, ?User $user): array
    {
        $enrollment = $user?->enrollments()
            ->where('course_id', $course->id)
            ->where('payment_status', 'completed')
            ->first();

        $sections = $course->sections()
            ->with(['lessons' => function ($query) use ($enrollment) {
                if (!$enrollment) {
                    $query->where('is_preview', true);
                }
            }])
            ->visible()
            ->orderBy('order')
            ->get();

        return [
            'sections' => $sections,
            'is_enrolled' => (bool) $enrollment,
            'progress' => $enrollment?->progress ?? 0,
            'total_duration' => $course->total_duration,
            'total_lessons' => $course->total_lessons,
        ];
    }

    /**
     * Get comprehensive analytics for a course
     */
    public function getAnalytics(Course $course): array
    {
        return Cache::remember(
            "course_analytics_{$course->id}",
            3600,
            function () use ($course) {
                $completedEnrollments = $course->enrollments()->completed();

                return [
                    'total_students' => $course->enrollments()->count(),
                    'completed_students' => $completedEnrollments->count(),
                    'total_revenue' => $completedEnrollments->sum('paid_amount'),
                    'average_rating' => $course->reviews()->approved()->avg('rating') ?? 0,
                    'total_reviews' => $course->reviews()->approved()->count(),
                    'completion_rate' => $this->calculateCompletionRate($course),
                    'monthly_growth' => $this->getMonthlyGrowth($course),
                    'top_lessons' => $this->getTopLessons($course),
                ];
            }
        );
    }

    /**
     * Private: Process media uploads
     */
    private function processCourseMedia(Course $course, StoreCourseRequest $request): void
    {
        if ($request->hasFile('thumbnail')) {
            $course->clearMediaCollection('thumbnail');
            $course->addMedia($request->file('thumbnail'))->toMediaCollection('thumbnail');
        }

        if ($request->filled('intro_video_url')) {
            $videoData = $this->videoService->parseVideoUrl($request->intro_video_url);
            $course->update(['intro_video_url' => $videoData['embed_url']]);
        }
    }

    /**
     * Private: Build filtered query
     */
    private function buildQuery(array $filters)
    {
        $query = Course::published()->with(['instructor', 'category', 'media']);

        // Apply search filters
        if (!empty($filters['search'])) {
            $this->applySearch($query, $filters['search']);
        }

        // Apply dynamic filters
        foreach (['category_id', 'level', 'instructor_id'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        // Price range
        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $query->whereBetween('price', [
                $filters['price_min'] ?? 0,
                $filters['price_max'] ?? 99999
            ]);
        }

        // Rating minimum
        if (!empty($filters['rating'])) {
            $query->where('rating', '>=', $filters['rating']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($this->getSortableColumn($sortBy), $sortOrder);

        return $query;
    }

    private function applySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('subtitle', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    private function getSortableColumn(string $sortBy): string
    {
        return match($sortBy) {
            'popularity' => 'students_count',
            'rating' => 'rating',
            'price' => 'price',
            'duration' => 'duration',
            default => 'created_at',
        };
    }

    private function buildCacheKey(array $filters): string
    {
        ksort($filters);
        return 'courses_' . md5(json_encode($filters));
    }

    private function calculateCompletionRate(Course $course): float
    {
        $total = $course->enrollments()->count();
        return $total > 0 ? round(($course->enrollments()->completed()->count() / $total) * 100, 2) : 0;
    }

    private function getMonthlyGrowth(Course $course)
    {
        return $course->enrollments()
            ->selectRaw('DATE_FORMAT(enrolled_at, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->pluck('count', 'month')
            ->toArray();
    }

    private function getTopLessons(Course $course)
    {
        return $course->lessons()
            ->withCount(['progress as completed_count' => fn($q) => $q->where('is_completed', true)])
            ->orderByDesc('completed_count')
            ->limit(5)
            ->get()
            ->map(fn($lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'completion_count' => $lesson->completed_count,
            ])
            ->toArray();
    }

    private function clearCourseCache(Course $course): void
    {
        Cache::forget("course_{$course->id}_analytics");
        Cache::forget("course_{$course->id}_content");
        Cache::forget("courses_*"); // Clear all course listings
    }
}
