<?php

namespace App\Observers;

use App\Models\Course;
use App\Events\{CourseCreated, CoursePublished, CourseUpdated, CourseDeleted};
use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Support\Facades\Auth;

class CourseObserver
{
    /**
     * Handle the Course "creating" event.
     */
    // public function creating(Course $course): void
    // {
    //     // Generate slug if not provided
    //     if (empty($course->slug)) {
    //         $course->slug = \Illuminate\Support\Str::slug($course->title);

    //         // Ensure unique slug
    //         $originalSlug = $course->slug;
    //         $count = 1;
    //         while (Course::where('slug', $course->slug)->exists()) {
    //             $course->slug = $originalSlug . '-' . $count;
    //             $count++;
    //         }
    //     }

    //     // Set default status
    //     if (empty($course->status)) {
    //         $course->status = 'draft';
    //     }

    //     // Set instructor_id if not set
    //     if (!$course->instructor_id && auth()->check()) {
    //         $course->instructor_id = auth()->id();
    //     }

    //     Log::info('Creating course', [
    //         'title' => $course->title,
    //         'instructor_id' => $course->instructor_id,
    //     ]);
    // }

    public function creating(Course $course): void
{
    // Generate slug if not provided
    if (empty($course->slug)) {
        $course->slug = \Illuminate\Support\Str::slug($course->title);

        // Ensure unique slug
        $originalSlug = $course->slug;
        $count = 1;
        while (Course::where('slug', $course->slug)->exists()) {
            $course->slug = $originalSlug . '-' . $count;
            $count++;
        }
    }

    // Set default status
    if (empty($course->status)) {
        $course->status = 'draft';
    }

    // Set instructor_id if not set
    if (! $course->instructor_id && Auth::check()) {
        $course->instructor_id = Auth::id();
    }

    Log::info('Creating course', [
        'title' => $course->title,
        'instructor_id' => $course->instructor_id,
    ]);
}
    /**
     * Handle the Course "created" event.
     */
    public function created(Course $course): void
    {
        Log::info('Course created', [
            'id' => $course->id,
            'title' => $course->title,
            'instructor_id' => $course->instructor_id,
        ]);

        event(new CourseCreated($course));

        // Clear instructor's course cache
        Cache::forget("instructor_{$course->instructor_id}_courses");
        Cache::forget("courses_list");
    }

    /**
     * Handle the Course "updating" event.
     */
    public function updating(Course $course): void
    {
        $changes = $course->getDirty();

        // Check if course is being published
        if (isset($changes['is_published']) && $changes['is_published'] && !$course->getOriginal('is_published')) {
            Log::info('Course being published', [
                'id' => $course->id,
                'title' => $course->title,
            ]);
        }

        // Update slug if title changes
        if (isset($changes['title']) && !isset($changes['slug'])) {
            $newSlug = \Illuminate\Support\Str::slug($changes['title']);
            if ($newSlug !== $course->slug) {
                $course->slug = $newSlug;
            }
        }
    }

    /**
     * Handle the Course "updated" event.
     */
    public function updated(Course $course): void
    {
        $changes = $course->getChanges();

        Log::info('Course updated', [
            'id' => $course->id,
            'changes' => array_keys($changes),
        ]);

        event(new CourseUpdated($course, $changes));

        // Clear related caches
        $this->clearCourseCaches($course);

        // Check if published
        if (isset($changes['is_published']) && $course->is_published && $course->is_approved) {
            event(new CoursePublished($course));
        }
    }

    /**
     * Handle the Course "deleting" event.
     */
    public function deleting(Course $course): void
    {
        Log::warning('Course being deleted', [
            'id' => $course->id,
            'title' => $course->title,
            'instructor_id' => $course->instructor_id,
        ]);

        // Check if course has enrollments
        $enrollmentCount = $course->enrollments()->where('payment_status', 'completed')->count();
        if ($enrollmentCount > 0) {
            Log::warning('Deleting course with active enrollments', [
                'course_id' => $course->id,
                'enrollment_count' => $enrollmentCount,
            ]);
        }
    }

    /**
     * Handle the Course "deleted" event.
     */
    public function deleted(Course $course): void
    {
        Log::warning('Course deleted', [
            'id' => $course->id,
            'title' => $course->title,
        ]);

        event(new CourseDeleted($course->id, $course->title, $course->instructor_id));

        // Clear all related caches
        $this->clearCourseCaches($course);

        // Clear media
        $course->clearMediaCollection('thumbnail');
    }

    /**
     * Handle the Course "restored" event.
     */
    public function restored(Course $course): void
    {
        Log::info('Course restored', [
            'id' => $course->id,
            'title' => $course->title,
        ]);

        $this->clearCourseCaches($course);
    }

    /**
     * Handle the Course "force deleted" event.
     */
    public function forceDeleted(Course $course): void
    {
        Log::critical('Course force deleted', [
            'id' => $course->id,
            'title' => $course->title,
        ]);

        $this->clearCourseCaches($course);
    }

    /**
     * Clear course-related caches
     */
    protected function clearCourseCaches(Course $course): void
    {
        Cache::forget("course_{$course->id}");
        Cache::forget("course_{$course->slug}");
        Cache::forget("course_{$course->id}_analytics");
        Cache::forget("course_{$course->id}_sections");
        Cache::forget("course_{$course->id}_lessons");
        Cache::forget("instructor_{$course->instructor_id}_courses");
        Cache::forget("courses_list");
        Cache::forget("category_{$course->category_id}_courses");

        // Clear tenant-specific caches
        if (tenant()) {
            Cache::forget("tenant_" . tenant()->id . "_courses");
        }
    }
}
