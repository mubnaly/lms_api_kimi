<?php

namespace App\Events;

use App\Models\Course;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Course Created Event
 */
class CourseCreated
{
    use Dispatchable, SerializesModels;

    public Course $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
    }
}

/**
 * Course Published Event
 */
class CoursePublished
{
    use Dispatchable, SerializesModels;

    public Course $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
    }
}

/**
 * Course Approved Event
 */
class CourseApproved
{
    use Dispatchable, SerializesModels;

    public Course $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
    }
}

/**
 * Course Rejected Event
 */
class CourseRejected
{
    use Dispatchable, SerializesModels;

    public Course $course;
    public string $reason;

    public function __construct(Course $course, string $reason)
    {
        $this->course = $course;
        $this->reason = $reason;
    }
}

/**
 * Course Updated Event
 */
class CourseUpdated
{
    use Dispatchable, SerializesModels;

    public Course $course;
    public array $changes;

    public function __construct(Course $course, array $changes)
    {
        $this->course = $course;
        $this->changes = $changes;
    }
}

/**
 * Course Deleted Event
 */
class CourseDeleted
{
    use Dispatchable, SerializesModels;

    public int $courseId;
    public string $title;
    public int $instructorId;

    public function __construct(int $courseId, string $title, int $instructorId)
    {
        $this->courseId = $courseId;
        $this->title = $title;
        $this->instructorId = $instructorId;
    }
}
