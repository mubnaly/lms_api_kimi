<?php

namespace App\Policies;

use App\Models\{User, Enrollment};

class EnrollmentPolicy
{
    public function view(User $user, Enrollment $enrollment)
    {
        return $user->id === $enrollment->user_id || $user->hasRole('admin');
    }

    public function create(User $user)
    {
        return true; // Any authenticated user can enroll
    }

    public function updateProgress(User $user, Enrollment $enrollment)
    {
        return $user->id === $enrollment->user_id && $enrollment->payment_status === 'completed';
    }
}
