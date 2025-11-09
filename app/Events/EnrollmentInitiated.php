<?php

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Enrollment Initiated Event
 */
class EnrollmentInitiated
{
    use Dispatchable, SerializesModels;

    public Enrollment $enrollment;

    public function __construct(Enrollment $enrollment)
    {
        $this->enrollment = $enrollment;
    }
}

/**
 * Student Enrolled Event (from existing code)
 */
class EnrollmentCompleted
{
    use Dispatchable, SerializesModels;

    public Enrollment $enrollment;

    public function __construct(Enrollment $enrollment)
    {
        $this->enrollment = $enrollment;
    }
}

/**
 * Free Enrollment Completed Event (from existing code)
 */
class FreeEnrollmentCompleted extends EnrollmentCompleted {}

/**
 * Payment Verified Event (from existing code)
 */
class PaymentVerified extends EnrollmentCompleted {}

/**
 * Enrollment Cancelled Event
 */
class EnrollmentCancelled
{
    use Dispatchable, SerializesModels;

    public Enrollment $enrollment;
    public string $reason;

    public function __construct(Enrollment $enrollment, string $reason)
    {
        $this->enrollment = $enrollment;
        $this->reason = $reason;
    }
}

/**
 * Course Completed Event
 */
class CourseCompleted
{
    use Dispatchable, SerializesModels;

    public Enrollment $enrollment;

    public function __construct(Enrollment $enrollment)
    {
        $this->enrollment = $enrollment;
    }
}
