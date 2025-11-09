<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseSectionResource;
use App\Models\{Course, CourseSection};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Cache, Log, DB};

class CourseSectionController extends Controller
{
    protected int $cacheTtl = 300; // 5 minutes

    /**
     * Create new course section
     */
    public function store(Request $request, Course $course)
    {
        Gate::authorize('manage', $course);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'is_visible' => 'boolean',
        ]);

        $section = DB::transaction(function () use ($validated, $course, $request) {
            $order = $validated['order'] ?? $course->sections()->max('order') + 1;

            return $course->sections()->create([
                ...$validated,
                'order' => $order,
            ]);
        });

        Cache::forget("course_{$course->id}_sections");

        Log::info('Course section created', [
            'section_id' => $section->id,
            'course_id' => $course->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Section created successfully',
            'data' => new CourseSectionResource($section),
        ], 201);
    }

    /**
     * Update course section
     */
    public function update(Request $request, CourseSection $section)
    {
        Gate::authorize('update', $section);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'sometimes|required|integer|min:0',
            'is_visible' => 'boolean',
        ]);

        $section->update($validated);

        // Reorder other sections if order changed
        if (isset($validated['order'])) {
            $this->reorderSections($section);
        }

        Cache::forget("course_{$section->course_id}_sections");

        Log::info('Course section updated', [
            'section_id' => $section->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Section updated successfully',
            'data' => new CourseSectionResource($section->fresh()),
        ]);
    }

    /**
     * Delete course section
     */
    public function destroy(CourseSection $section)
    {
        Gate::authorize('delete', $section);

        DB::transaction(function () use ($section) {
            // Move lessons to previous section or create "Uncategorized"
            $this->handleOrphanedLessons($section);

            // Delete section
            $section->delete();

            // Reorder remaining sections
            $this->reorderRemainingSections($section->course);
        });

        Cache::forget("course_{$section->course_id}_sections");

        Log::info('Course section deleted', [
            'section_id' => $section->id,
            'course_id' => $section->course_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Section deleted successfully',
        ]);
    }

    /**
     * Reorder sections when order changes
     */
    protected function reorderSections(CourseSection $updatedSection): void
    {
        $sections = $updatedSection->course->sections()
            ->where('id', '!=', $updatedSection->id)
            ->orderBy('order')
            ->get();

        $newOrder = $updatedSection->order;
        $currentOrder = 0;

        foreach ($sections as $section) {
            $currentOrder++;
            if ($currentOrder === $newOrder) {
                $currentOrder++;
            }
            $section->update(['order' => $currentOrder]);
        }
    }

    /**
     * Handle orphaned lessons when section is deleted
     */
    protected function handleOrphanedLessons(CourseSection $section): void
    {
        $lessons = $section->lessons;

        if ($lessons->isEmpty()) {
            return;
        }

        // Find previous section or create "Uncategorized" section
        $previousSection = $section->course->sections()
            ->where('order', '<', $section->order)
            ->orderByDesc('order')
            ->first();

        if (!$previousSection) {
            $previousSection = $section->course->sections()->create([
                'title' => 'غير مصنف',
                'description' => 'دروس غير مصنفة',
                'order' => 0,
                'is_visible' => true,
            ]);
        }

        // Move all lessons to previous section
        $lessons->each(function ($lesson) use ($previousSection) {
            $lesson->update(['section_id' => $previousSection->id]);
        });
    }

    /**
     * Reorder remaining sections after deletion
     */
    protected function reorderRemainingSections(Course $course): void
    {
        $sections = $course->sections()->orderBy('order')->get();

        foreach ($sections as $index => $section) {
            $section->update(['order' => $index + 1]);
        }
    }

    /**
     * Bulk reorder sections
     */
    public function reorder(Request $request, Course $course)
    {
        Gate::authorize('manage', $course);

        $validated = $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:course_sections,id',
            'sections.*.order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $course) {
            foreach ($validated['sections'] as $sectionData) {
                $section = $course->sections()->find($sectionData['id']);
                $section->update(['order' => $sectionData['order']]);
            }
        });

        Cache::forget("course_{$course->id}_sections");

        return response()->json([
            'success' => true,
            'message' => 'Sections reordered successfully',
        ]);
    }

    /**
     * Duplicate section with lessons
     */
    public function duplicate(Request $request, CourseSection $section)
    {
        Gate::authorize('duplicate', $section);

        $newSection = DB::transaction(function () use ($section) {
            // Create copy of section
            $newSection = $section->replicate();
            $newSection->title = $section->title . ' (نسخة)';
            $newSection->order = $section->course->sections()->max('order') + 1;
            $newSection->save();

            // Copy all lessons
            foreach ($section->lessons as $lesson) {
                $newLesson = $lesson->replicate();
                $newLesson->section_id = $newSection->id;
                $newLesson->save();

                // Copy lesson media if exists
                foreach ($lesson->media as $media) {
                    $media->copy($newLesson, 'lessons');
                }
            }

            return $newSection;
        });

        Cache::forget("course_{$section->course_id}_sections");

        return response()->json([
            'success' => true,
            'message' => 'Section duplicated successfully',
            'data' => new CourseSectionResource($newSection->load('lessons')),
        ], 201);
    }

    /**
     * Toggle section visibility
     */
    public function toggleVisibility(CourseSection $section)
    {
        Gate::authorize('update', $section);

        $section->update(['is_visible' => !$section->is_visible]);

        Cache::forget("course_{$section->course_id}_sections");

        return response()->json([
            'success' => true,
            'message' => 'Section visibility updated',
            'data' => [
                'is_visible' => $section->is_visible,
            ],
        ]);
    }
}
