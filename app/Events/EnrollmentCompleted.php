<?php

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnrollmentCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Enrollment $enrollment) {}
}
