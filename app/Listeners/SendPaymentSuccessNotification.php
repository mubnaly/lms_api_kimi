<?php

namespace App\Listeners;

use App\Events\{
    PaymentCompleted,
    EnrollmentCompleted,
    CourseCompleted,
    InstructorApproved
};
use App\Notifications\{
    PaymentSuccessNotification,
    EnrollmentConfirmation,
    CourseCompletionCertificate,
    InstructorApprovalNotification
};
use Illuminate\Support\Facades\Log;

/**
 * Payment Completed Listener
 */
class SendPaymentSuccessNotification
{
    public function handle(PaymentCompleted $event): void
    {
        $enrollment = $event->enrollment;

        Log::info('Sending payment success notification', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
        ]);

        $enrollment->user->notify(new PaymentSuccessNotification($enrollment));
    }
}

/**
 * Enrollment Completed Listener
 */
class SendEnrollmentConfirmation
{
    public function handle(EnrollmentCompleted $event): void
    {
        $enrollment = $event->enrollment;

        Log::info('Sending enrollment confirmation', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
        ]);

        $enrollment->user->notify(new EnrollmentConfirmation($enrollment));

        // Notify instructor
        $enrollment->course->instructor->notify(
            new \App\Notifications\NewStudentEnrolledNotification($enrollment)
        );
    }
}

/**
 * Course Completed Listener
 */
class SendCompletionCertificate
{
    public function handle(CourseCompleted $event): void
    {
        $enrollment = $event->enrollment;

        Log::info('Sending completion certificate', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
        ]);

        $enrollment->user->notify(new CourseCompletionCertificate($enrollment));

        // Generate certificate PDF
        app(\App\Services\CertificateService::class)->generate($enrollment);
    }
}

/**
 * Instructor Approved Listener
 */
class NotifyInstructorApproval
{
    public function handle(InstructorApproved $event): void
    {
        $instructor = $event->instructor;

        Log::info('Notifying instructor approval', [
            'instructor_id' => $instructor->id,
        ]);

        $instructor->notify(new InstructorApprovalNotification($instructor));

        // Create instructor dashboard
        app(\App\Services\InstructorOnboardingService::class)->setupDashboard($instructor);
    }
}

/**
 * Payment Failed Listener
 */
class LogPaymentFailure
{
    public function handle(\App\Events\PaymentFailed $event): void
    {
        Log::error('Payment failed', [
            'enrollment_id' => $event->enrollment->id,
            'reason' => $event->reason,
            'user_id' => $event->enrollment->user_id,
            'course_id' => $event->enrollment->course_id,
        ]);

        // Notify admin
        \Illuminate\Support\Facades\Notification::route('mail', config('mail.admin_email'))
            ->notify(new \App\Notifications\PaymentFailureNotification($event->enrollment, $event->reason));
    }
}

/**
 * Course Published Listener
 */
class NotifyCoursePublished
{
    public function handle(\App\Events\CoursePublished $event): void
    {
        $course = $event->course;

        Log::info('Course published', [
            'course_id' => $course->id,
            'instructor_id' => $course->instructor_id,
        ]);

        // Notify instructor
        $course->instructor->notify(
            new \App\Notifications\CoursePublishedNotification($course)
        );

        // Notify followers (if feature exists)
        $this->notifyFollowers($course);
    }

    protected function notifyFollowers($course): void
    {
        // Implement follower notification logic
    }
}

/**
 * User Registered Listener
 */
class SendWelcomeEmail
{
    public function handle(\App\Events\UserRegistered $event): void
    {
        $user = $event->user;

        Log::info('Sending welcome email', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $user->notify(new \App\Notifications\WelcomeNotification($user));
    }
}

/**
 * Instructor Application Listener
 */
class NotifyAdminOfInstructorApplication
{
    public function handle(\App\Events\InstructorApplicationSubmitted $event): void
    {
        Log::info('Notifying admin of instructor application', [
            'user_id' => $event->user->id,
        ]);

        // Notify all admins
        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(
                new \App\Notifications\NewInstructorApplicationNotification($event->user)
            );
        }
    }
}
