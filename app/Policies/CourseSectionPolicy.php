<?php

namespace App\Policies;

use App\Models\{User, CourseSection};

class CourseSectionPolicy
{
    public function viewAny(User $user, $course)
    {
        return $user->id === $course->instructor_id || $user->hasRole('admin');
    }

    public function create(User $user, $course)
    {
        return $user->id === $course->instructor_id || $user->hasRole('admin');
    }

    public function update(User $user, CourseSection $section)
    {
        return $user->id === $section->course->instructor_id || $user->hasRole('admin');
    }

    public function delete(User $user, CourseSection $section)
    {
        return $user->id === $section->course->instructor_id || $user->hasRole('admin');
    }

    public function duplicate(User $user, CourseSection $section)
    {
        return $this->update($user, $section);
    }
}
