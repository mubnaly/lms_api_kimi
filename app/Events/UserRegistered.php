<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * User Registered Event
 */
class UserRegistered
{
    use Dispatchable, SerializesModels;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}

/**
 * User Verified Event
 */
class UserVerified
{
    use Dispatchable, SerializesModels;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}

/**
 * Instructor Application Submitted Event
 */
class InstructorApplicationSubmitted
{
    use Dispatchable, SerializesModels;

    public User $user;
    public array $applicationData;

    public function __construct(User $user, array $applicationData)
    {
        $this->user = $user;
        $this->applicationData = $applicationData;
    }
}

/**
 * Instructor Approved Event
 */
class InstructorApproved
{
    use Dispatchable, SerializesModels;

    public User $instructor;

    public function __construct(User $instructor)
    {
        $this->instructor = $instructor;
    }
}

/**
 * Instructor Rejected Event
 */
class InstructorRejected
{
    use Dispatchable, SerializesModels;

    public User $instructor;
    public string $reason;

    public function __construct(User $instructor, string $reason)
    {
        $this->instructor = $instructor;
        $this->reason = $reason;
    }
}

/**
 * User Profile Updated Event
 */
class UserProfileUpdated
{
    use Dispatchable, SerializesModels;

    public User $user;
    public array $changes;

    public function __construct(User $user, array $changes)
    {
        $this->user = $user;
        $this->changes = $changes;
    }
}
