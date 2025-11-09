<?php

namespace App\Services;

use App\Models\{Course, User, Enrollment, Category, Lesson};
use Illuminate\Support\Facades\{DB, Cache, Storage};
use Spatie\QueryBuilder\QueryBuilder;

class CourseService
{
    protected $cacheTtl = 3600; // 1 hour

    /**
     * Create new course with all media
     */
    public function createCourse(array $data, User $instructor): Course
    {
        return DB::transaction(function () use ($data, $instructor) {
            $course = $instructor->courses()->create([
                ...$data,
                'status' => 'draft',
                'is_published' => false,
                'is_approved' => false,
                'students_count' => 0,
                'rating' => 0.00,
                'reviews_count' => 0,
            ]);

            $this->handleCourseMedia($course, $data);

            return $course->fresh(['media', 'instructor']);
        });
    }

    /**
     * Update existing course
     */
    public function updateCourse(Course $course, array $data): Course
    {
        return DB::transaction(function () use ($course, $data) {
            $course->update($data);

            $this->handleCourseMedia($course, $data);
            $this->updateCourseDuration($course);

            Cache::forget("course_{$course->id}_analytics");

            return $course->fresh(['media', 'instructor', 'category']);
        });
    }

    /**
     * Handle course media (thumbnail)
     */
    protected function handleCourseMedia(Course $course, array $data): void
    {
        if (isset($data['thumbnail']) && $data['thumbnail']->isValid()) {
            $course->clearMediaCollection('thumbnail');
            $course->addMedia($data['thumbnail'])
                ->toMediaCollection('thumbnail');
        }
    }

    /**
     * Recalculate total course duration
     */
    public function updateCourseDuration(Course $course): void
    {
        $totalDuration = $course->lessons()->sum('duration');
        $course->update(['duration' => $totalDuration]);
    }

    /**
     * Search courses with advanced filters and caching
     */
    public function search(array $filters)
    {
        $cacheKey = 'courses_search_' . md5(json_encode($filters));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($filters) {
            $query = $this->buildCourseQuery($filters);
            return $this->applySorting($query, $filters);
        });
    }

    /**
     * Build base query with filters
     */
    protected function buildCourseQuery(array $filters)
    {
        return QueryBuilder::for(Course::published()->with(['instructor', 'category']))
        ->allowedFilters([
            'title',
            'instructor_id',
            'level',
            'language',
        ])
        ->where(function ($query) use ($filters) {
            // Search in title, subtitle, description
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->whereRaw("MATCH(title, subtitle, description) AGAINST(? IN BOOLEAN MODE)", [$search]);
            }

            // Category filter (including subcategories)
            if (!empty($filters['category'])) {
                $category = \App\Models\Category::find($filters['category']);
                if ($category) {
                    $categoryIds = $category->getAllChildIds([$category->id]);
                    $query->whereIn('category_id', $categoryIds);
                }
            }

            // Price range
            if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
                $query->whereBetween('price', [
                    $filters['price_min'] ?? 0,
                    $filters['price_max'] ?? 999999
                ]);
            }
    });
    }

    /**
     * Apply sorting
     */
    protected function applySorting($query, array $filters)
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($this->getSortableColumn($sortBy), $sortOrder);
    }

    protected function getSortableColumn(string $sortBy): string
    {
        return match($sortBy) {
            'popularity' => 'students_count',
            'rating' => 'rating',
            'price' => 'price',
            'duration' => 'duration',
            default => 'created_at',
        };
    }

    /**
     * Get course analytics with caching
     */
    public function getAnalytics(Course $course): array
    {
        return Cache::remember(
            "course_{$course->id}_analytics",
            $this->cacheTtl,
            fn() => $this->calculateAnalytics($course)
        );
    }

    protected function calculateAnalytics(Course $course): array
    {
        $enrollments = $course->enrollments();
        $completedEnrollments = $enrollments->clone()->where('progress', 100);

        return [
            'total_students' => $enrollments->count(),
            'completed_students' => $completedEnrollments->count(),
            'total_revenue' => $enrollments->completed()->sum('paid_amount'),
            'average_rating' => $course->reviews()->approved()->avg('rating') ?? 0,
            'total_reviews' => $course->reviews()->approved()->count(),
            'completion_rate' => $this->calculateCompletionRate($course),
            'monthly_enrollments' => $this->getMonthlyEnrollments($course),
            'top_performing_lessons' => $this->getTopLessons($course),
        ];
    }

    protected function calculateCompletionRate(Course $course): float
    {
        $total = $course->enrollments()->count();
        if ($total === 0) return 0;

        $completed = $course->enrollments()->where('progress', 100)->count();
        return round(($completed / $total) * 100, 2);
    }

    protected function getMonthlyEnrollments(Course $course): array
    {
        return $course->enrollments()
            ->selectRaw("
                DATE_FORMAT(enrolled_at, '%Y-%m') as month,
                COUNT(*) as count
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn($item) => [$item->month => $item->count])
            ->toArray();
    }

    protected function getTopLessons(Course $course): array
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

    /**
     * Update course statistics (rating, students count)
     */
    public function updateCourseStatistics(Course $course): void
    {
        $course->update([
            'students_count' => $course->enrollments()->count(),
            'rating' => $course->reviews()->approved()->avg('rating') ?? 0,
            'reviews_count' => $course->reviews()->approved()->count(),
        ]);

        Cache::forget("course_{$course->id}_analytics");
    }

    /**
     * Delete course with cascade cleanup
     */
    public function deleteCourse(Course $course): bool
    {
        return DB::transaction(function () use ($course) {
            // Delete relationships
            $course->enrollments()->delete();
            $course->reviews()->delete();
            $course->coupons()->delete();
            $course->wishlistUsers()->detach();

            // Clear media
            $course->clearMediaCollection('thumbnail');

            // Delete course
            return $course->delete();
        });
    }
}
