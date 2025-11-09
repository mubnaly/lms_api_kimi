<?php

namespace App\Policies;

use App\Models\{User, Course};

class CoursePolicy
{
    /**
     * Determine if user can view any courses
     */
    public function viewAny(?User $user): bool
    {
        return true; // Public endpoint
    }

    /**
     * Determine if user can view the course
     */
    public function view(?User $user, Course $course): bool
    {
        // Published courses are public
        if ($course->is_published && $course->is_approved) {
            return true;
        }

        // Instructor can view their own unpublished courses
        if ($user && $user->id === $course->instructor_id) {
            return true;
        }

        // Admin can view all courses
        return $user && $user->hasRole('admin');
    }

    /**
     * Determine if user can create courses
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['instructor', 'admin']) && $user->is_verified;
    }

    /**
     * Determine if user can update the course
     */
    public function update(User $user, Course $course): bool
    {
        return $user->id === $course->instructor_id || $user->hasRole('admin');
    }

    /**
     * Determine if user can delete the course
     */
    public function delete(User $user, Course $course): bool
    {
        // Only owner or admin can delete
        if ($user->id === $course->instructor_id || $user->hasRole('admin')) {
            // Cannot delete if there are active enrollments
            return $course->enrollments()->where('payment_status', 'completed')->count() === 0;
        }

        return false;
    }

    /**
     * Determine if user can manage course content
     */
    public function manage(User $user, Course $course): bool
    {
        return $user->id === $course->instructor_id || $user->hasRole('admin');
    }

    /**
     * Determine if user can view analytics
     */
    public function viewAnalytics(User $user, Course $course): bool
    {
        return $user->id === $course->instructor_id || $user->hasRole('admin');
    }

    /**
     * Determine if user can enroll in course
     */
    public function enroll(User $user, Course $course): bool
    {
        // Cannot enroll in own course
        if ($user->id === $course->instructor_id) {
            return false;
        }

        // Course must be published and approved
        if (!$course->is_published || !$course->is_approved) {
            return false;
        }

        // Check if already enrolled
        return !$user->enrollments()
            ->where('course_id', $course->id)
            ->exists();
    }

    /**
     * Admin can approve courses
     */
    public function approve(User $user, Course $course): bool
    {
        return $user->hasRole('admin');
    }
}
