<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLessonProgressRequest;
use App\Models\{Course, Lesson, LessonProgress, Enrollment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LessonProgressController extends Controller
{
    /**
     * Update lesson progress (watched duration)
     */
    public function update(UpdateLessonProgressRequest $request, Course $course, Lesson $lesson)
    {
        $enrollment = $this->getValidEnrollment($request->user(), $course);

        $progress = LessonProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'watched_seconds' => $request->watched_seconds,
                'last_watched_at' => now(),
            ]
        );

        // Auto-complete if 90% watched
        if ($request->watched_seconds >= ($lesson->duration * 0.9)) {
            $progress->markAsCompleted();
        }

        // Update overall course progress
        $newProgress = $enrollment->calculateProgress();
        $enrollment->update(['progress' => $newProgress]);

        return response()->json([
            'success' => true,
            'data' => [
                'lesson_progress' => $progress->fresh(),
                'course_progress' => $newProgress,
                'is_course_completed' => $newProgress >= 100,
            ],
        ]);
    }

    /**
     * Mark lesson as completed manually
     */
    public function complete(Request $request, Course $course, Lesson $lesson)
    {
        $enrollment = $this->getValidEnrollment($request->user(), $course);

        $progress = LessonProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'is_completed' => true,
                'progress' => 100,
                'watched_seconds' => $lesson->duration,
                'last_watched_at' => now(),
            ]
        );

        $newProgress = $enrollment->calculateProgress();
        $enrollment->update(['progress' => $newProgress]);

        return response()->json([
            'success' => true,
            'message' => 'Lesson marked as completed',
            'data' => [
                'course_progress' => $newProgress,
                'is_course_completed' => $newProgress >= 100,
            ],
        ]);
    }

    /**
     * Get valid enrollment for user and course
     */
    protected function getValidEnrollment(User $user, Course $course): Enrollment
    {
        return $user->enrollments()
            ->where('course_id', $course->id)
            ->where('payment_status', 'completed')
            ->firstOr(function () {
                abort(403, 'You must be enrolled in this course to access lessons');
            });
    }
}
