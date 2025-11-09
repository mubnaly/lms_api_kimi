<?php

namespace App\Policies;

use App\Models\{User, Lesson};

class LessonPolicy
{
    public function view(User $user, Lesson $lesson)
    {
        $course = $lesson->course;

        // Check if user is enrolled
        $isEnrolled = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('payment_status', 'completed')
            ->exists();

        if ($isEnrolled) {
            return true;
        }

        // Check if lesson is preview
        if ($lesson->is_preview) {
            return true;
        }

        // Check if user is instructor or admin
        return $user->id === $course->instructor_id || $user->hasRole('admin');
    }

    public function create(User $user, $section)
    {
        return $user->id === $section->course->instructor_id || $user->hasRole('admin');
    }

    public function update(User $user, Lesson $lesson)
    {
        return $user->id === $lesson->course->instructor_id || $user->hasRole('admin');
    }

    public function delete(User $user, Lesson $lesson)
    {
        return $this->update($user, $lesson);
    }

    public function duplicate(User $user, Lesson $lesson)
    {
        return $this->update($user, $lesson);
    }
}
