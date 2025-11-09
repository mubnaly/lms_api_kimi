<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\{Course, CourseSection, Lesson, Enrollment};
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Cache, Log, DB, Storage};

class LessonController extends Controller
{
    protected int $cacheTtl = 300; // 5 minutes

    public function __construct(protected VideoService $videoService) {}

    /**
     * Create new lesson in section
     */
    public function store(Request $request, CourseSection $section)
    {
        Gate::authorize('manage', $section->course);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:video,document,quiz,text',
            'content' => 'nullable|string',
            'video_url' => 'required_if:type,video|nullable|url',
            'duration' => 'required|integer|min:1|max:86400', // Max 24 hours
            'is_preview' => 'boolean',
            'is_free' => 'boolean',
            'order' => 'nullable|integer|min:0',
            'is_visible' => 'boolean',
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx|max:10240', // 10MB
        ]);

        $lesson = DB::transaction(function () use ($validated, $section, $request) {
            $order = $validated['order'] ?? $section->lessons()->max('order') + 1;

            $lessonData = [
                ...$validated,
                'course_id' => $section->course_id,
                'order' => $order,
            ];

            // Process video URL if provided
            if ($request->filled('video_url') && $validated['type'] === 'video') {
                $videoData = $this->videoService->parseVideoUrl($validated['video_url']);
                $lessonData = array_merge($lessonData, [
                    'video_url' => $videoData['embed_url'],
                    'video_platform' => $videoData['platform'],
                    'video_id' => $videoData['video_id'],
                ]);
            }

            $lesson = $section->lessons()->create($lessonData);

            // Handle document upload
            if ($request->hasFile('document_file') && $validated['type'] === 'document') {
                $lesson->addMedia($request->file('document_file'))
                    ->toMediaCollection('documents');
            }

            return $lesson;
        });

        // Update course total duration
        $this->updateCourseDuration($section->course);

        Cache::forget("course_{$section->course_id}_lessons");

        Log::info('Lesson created', [
            'lesson_id' => $lesson->id,
            'section_id' => $section->id,
            'course_id' => $section->course_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lesson created successfully',
            'data' => new LessonResource($lesson),
        ], 201);
    }

    /**
     * Update lesson
     */
    public function update(Request $request, Lesson $lesson)
    {
        Gate::authorize('manage', $lesson->course);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|in:video,document,quiz,text',
            'content' => 'nullable|string',
            'video_url' => 'required_if:type,video|nullable|url',
            'duration' => 'sometimes|required|integer|min:1|max:86400',
            'is_preview' => 'boolean',
            'is_free' => 'boolean',
            'order' => 'sometimes|required|integer|min:0',
            'is_visible' => 'boolean',
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx|max:10240',
        ]);

        DB::transaction(function () use ($validated, $lesson, $request) {
            $lessonData = $validated;

            // Handle video URL changes
            if ($request->filled('video_url') && ($validated['type'] ?? $lesson->type) === 'video') {
                $videoData = $this->videoService->parseVideoUrl($validated['video_url']);
                $lessonData = array_merge($lessonData, [
                    'video_url' => $videoData['embed_url'],
                    'video_platform' => $videoData['platform'],
                    'video_id' => $videoData['video_id'],
                ]);
            }

            // Handle document file changes
            if ($request->hasFile('document_file') && ($validated['type'] ?? $lesson->type) === 'document') {
                $lesson->clearMediaCollection('documents');
                $lesson->addMedia($request->file('document_file'))
                    ->toMediaCollection('documents');
            }

            $lesson->update($lessonData);

            // Reorder lessons if order changed
            if (isset($validated['order'])) {
                $this->reorderLessons($lesson);
            }
        });

        // Update course duration
        $this->updateCourseDuration($lesson->course);

        Cache::forget("course_{$lesson->course_id}_lessons");

        Log::info('Lesson updated', [
            'lesson_id' => $lesson->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lesson updated successfully',
            'data' => new LessonResource($lesson->fresh()),
        ]);
    }

    /**
     * Delete lesson
     */
    public function destroy(Lesson $lesson)
    {
        Gate::authorize('manage', $lesson->course);

        DB::transaction(function () use ($lesson) {
            // Clear media collections
            $lesson->clearMediaCollection('documents');

            // Delete lesson
            $lesson->delete();
        });

        // Update course duration
        $this->updateCourseDuration($lesson->course);

        // Reorder remaining lessons
        $this->reorderRemainingLessons($lesson->section);

        Cache::forget("course_{$lesson->course_id}_lessons");

        Log::info('Lesson deleted', [
            'lesson_id' => $lesson->id,
            'section_id' => $lesson->section_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lesson deleted successfully',
        ]);
    }

    /**
     * Reorder lessons when order changes
     */
    protected function reorderLessons(Lesson $updatedLesson): void
    {
        $lessons = $updatedLesson->section->lessons()
            ->where('id', '!=', $updatedLesson->id)
            ->orderBy('order')
            ->get();

        $newOrder = $updatedLesson->order;
        $currentOrder = 0;

        foreach ($lessons as $lesson) {
            $currentOrder++;
            if ($currentOrder === $newOrder) {
                $currentOrder++;
            }
            $lesson->update(['order' => $currentOrder]);
        }
    }

    /**
     * Reorder remaining lessons after deletion
     */
    protected function reorderRemainingLessons(CourseSection $section): void
    {
        $lessons = $section->lessons()->orderBy('order')->get();

        foreach ($lessons as $index => $lesson) {
            $lesson->update(['order' => $index + 1]);
        }
    }

    /**
     * Update course total duration
     */
    protected function updateCourseDuration(Course $course): void
    {
        $totalDuration = $course->lessons()->sum('duration');
        $course->update(['duration' => $totalDuration]);
    }

    /**
     * Bulk reorder lessons
     */
    public function reorder(Request $request, CourseSection $section)
    {
        Gate::authorize('manage', $section->course);

        $validated = $request->validate([
            'lessons' => 'required|array',
            'lessons.*.id' => 'required|exists:lessons,id',
            'lessons.*.order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $section) {
            foreach ($validated['lessons'] as $lessonData) {
                $lesson = $section->lessons()->find($lessonData['id']);
                $lesson->update(['order' => $lessonData['order']]);
            }
        });

        Cache::forget("course_{$section->course_id}_lessons");

        return response()->json([
            'success' => true,
            'message' => 'Lessons reordered successfully',
        ]);
    }

    /**
     * Duplicate lesson
     */
    public function duplicate(Request $request, Lesson $lesson)
    {
        Gate::authorize('duplicate', $lesson);

        $newLesson = DB::transaction(function () use ($lesson) {
            // Create copy of lesson
            $newLesson = $lesson->replicate();
            $newLesson->title = $lesson->title . ' (نسخة)';
            $newLesson->order = $lesson->section->lessons()->max('order') + 1;
            $newLesson->save();

            // Copy media
            foreach ($lesson->media as $media) {
                $media->copy($newLesson, 'lessons');
            }

            return $newLesson;
        });

        Cache::forget("course_{$lesson->course_id}_lessons");

        return response()->json([
            'success' => true,
            'message' => 'Lesson duplicated successfully',
            'data' => new LessonResource($newLesson->load('media')),
        ], 201);
    }

    /**
     * Toggle lesson visibility
     */
    public function toggleVisibility(Lesson $lesson)
    {
        Gate::authorize('update', $lesson);

        $lesson->update(['is_visible' => !$lesson->is_visible]);

        Cache::forget("course_{$lesson->course_id}_lessons");

        return response()->json([
            'success' => true,
            'message' => 'Lesson visibility updated',
            'data' => [
                'is_visible' => $lesson->is_visible,
            ],
        ]);
    }

    /**
     * Mark lesson as preview
     */
    public function markAsPreview(Lesson $lesson)
    {
        Gate::authorize('update', $lesson);

        // Remove preview from other lessons in course
        $lesson->course->lessons()->update(['is_preview' => false]);

        $lesson->update(['is_preview' => true]);

        Cache::forget("course_{$lesson->course_id}_lessons");

        return response()->json([
            'success' => true,
            'message' => 'Lesson marked as preview',
            'data' => [
                'is_preview' => $lesson->is_preview,
            ],
        ]);
    }

    /**
     * Get lesson with completion status
     */
    public function show(Request $request, Course $course, Lesson $lesson)
    {
        $this->authorize('view', $lesson);

        $enrollment = $request->user()?->enrollments()
            ->where('course_id', $course->id)
            ->where('payment_status', 'completed')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'lesson' => new LessonResource($lesson->load('media')),
                'is_enrolled' => (bool) $enrollment,
                'progress' => $enrollment?->lessonProgress()
                    ->where('lesson_id', $lesson->id)
                    ->first()?->progress ?? 0,
                'is_completed' => $enrollment?->lessonProgress()
                    ->where('lesson_id', $lesson->id)
                    ->where('is_completed', true)
                    ->exists() ?? false,
            ],
        ]);
    }

    /**
     * Get lesson content (for enrolled users)
     */
    public function content(Request $request, Course $course, Lesson $lesson)
    {
        $enrollment = $request->user()?->enrollments()
            ->where('course_id', $course->id)
            ->where('payment_status', 'completed')
            ->first();

        if (!$enrollment && !$lesson->is_preview) {
            abort(403, 'This lesson is not available for preview');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'lesson' => new LessonResource($lesson->load('media')),
                'is_enrolled' => (bool) $enrollment,
                'next_lesson' => $this->getNextLesson($lesson, $enrollment),
                'previous_lesson' => $this->getPreviousLesson($lesson, $enrollment),
            ],
        ]);
    }

    /**
     * Get next lesson in sequence
     */
    protected function getNextLesson(Lesson $currentLesson, ?Enrollment $enrollment): ?array
    {
        $query = $currentLesson->section->lessons()
            ->where('order', '>', $currentLesson->order)
            ->visible();

        if (!$enrollment) {
            $query->where('is_preview', true);
        }

        $nextLesson = $query->first();

        if (!$nextLesson) {
            // Check next section
            $nextSection = $currentLesson->course->sections()
                ->where('order', '>', $currentLesson->section->order)
                ->visible()
                ->first();

            if ($nextSection) {
                $query = $nextSection->lessons()->visible()->ordered();

                if (!$enrollment) {
                    $query->where('is_preview', true);
                }

                $nextLesson = $query->first();
            }
        }

        return $nextLesson ? [
            'id' => $nextLesson->id,
            'title' => $nextLesson->title,
            'slug' => $nextLesson->slug,
        ] : null;
    }

    /**
     * Get previous lesson in sequence
     */
    protected function getPreviousLesson(Lesson $currentLesson, ?Enrollment $enrollment): ?array
    {
        $query = $currentLesson->section->lessons()
            ->where('order', '<', $currentLesson->order)
            ->visible()
            ->orderByDesc('order');

        if (!$enrollment) {
            $query->where('is_preview', true);
        }

        $previousLesson = $query->first();

        if (!$previousLesson) {
            // Check previous section
            $previousSection = $currentLesson->course->sections()
                ->where('order', '<', $currentLesson->section->order)
                ->visible()
                ->orderByDesc('order')
                ->first();

            if ($previousSection) {
                $query = $previousSection->lessons()->visible()->orderByDesc('order');

                if (!$enrollment) {
                    $query->where('is_preview', true);
                }

                $previousLesson = $query->first();
            }
        }

        return $previousLesson ? [
            'id' => $previousLesson->id,
            'title' => $previousLesson->title,
            'slug' => $previousLesson->slug,
        ] : null;
    }
}
