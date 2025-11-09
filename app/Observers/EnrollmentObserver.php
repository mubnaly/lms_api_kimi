<?php

namespace App\Observers;

use App\Models\Enrollment;
use App\Events\{EnrollmentInitiated, EnrollmentCompleted, CourseCompleted};
use Illuminate\Support\Facades\{Cache, Log, Notification};
use App\Notifications\{EnrollmentConfirmation, CourseCompletionCertificate};

class EnrollmentObserver
{
    /**
     * Handle the Enrollment "creating" event.
     */
    public function creating(Enrollment $enrollment): void
    {
        // Set default values
        if (!isset($enrollment->enrolled_at)) {
            $enrollment->enrolled_at = now();
        }

        if (!isset($enrollment->progress)) {
            $enrollment->progress = 0;
        }

        Log::info('Creating enrollment', [
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'amount' => $enrollment->paid_amount,
        ]);
    }

    /**
     * Handle the Enrollment "created" event.
     */
    public function created(Enrollment $enrollment): void
    {
        Log::info('Enrollment created', [
            'id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'status' => $enrollment->payment_status,
        ]);

        event(new EnrollmentInitiated($enrollment));

        // Clear user's enrollment cache
        Cache::forget("user_{$enrollment->user_id}_enrollments");
        Cache::forget("user_{$enrollment->user_id}_courses");
    }

    /**
     * Handle the Enrollment "updating" event.
     */
    public function updating(Enrollment $enrollment): void
    {
        $changes = $enrollment->getDirty();

        // Check if payment is being completed
        if (isset($changes['payment_status']) &&
            $changes['payment_status'] === 'completed' &&
            $enrollment->getOriginal('payment_status') !== 'completed') {

            Log::info('Enrollment payment completed', [
                'id' => $enrollment->id,
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
            ]);

            // Update course enrollment count
            if (!$enrollment->getOriginal('payment_status') ||
                $enrollment->getOriginal('payment_status') !== 'completed') {
                $enrollment->course->increment('students_count');
            }
        }

        // Check if progress reaches 100%
        if (isset($changes['progress']) &&
            $changes['progress'] >= 100 &&
            $enrollment->getOriginal('progress') < 100) {

            Log::info('Course progress completed', [
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
            ]);

            $enrollment->completed_at = now();
        }
    }

    /**
     * Handle the Enrollment "updated" event.
     */
    public function updated(Enrollment $enrollment): void
    {
        $changes = $enrollment->getChanges();

        Log::info('Enrollment updated', [
            'id' => $enrollment->id,
            'changes' => array_keys($changes),
        ]);

        // Clear caches
        $this->clearEnrollmentCaches($enrollment);

        // Send notification if payment completed
        if (isset($changes['payment_status']) && $enrollment->payment_status === 'completed') {
            event(new EnrollmentCompleted($enrollment));

            // Send enrollment confirmation
            $enrollment->user->notify(new EnrollmentConfirmation($enrollment));
        }

        // Send certificate if course completed
        if (isset($changes['completed_at']) && $enrollment->completed_at) {
            event(new CourseCompleted($enrollment));

            // Send completion certificate
            $enrollment->user->notify(new CourseCompletionCertificate($enrollment));
        }
    }

    /**
     * Handle the Enrollment "deleting" event.
     */
    public function deleting(Enrollment $enrollment): void
    {
        Log::warning('Enrollment being deleted', [
            'id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
        ]);

        // Decrement course enrollment count if payment was completed
        if ($enrollment->payment_status === 'completed') {
            $enrollment->course->decrement('students_count');
        }
    }

    /**
     * Handle the Enrollment "deleted" event.
     */
    public function deleted(Enrollment $enrollment): void
    {
        Log::warning('Enrollment deleted', [
            'id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
        ]);

        // Delete related progress records
        $enrollment->lessonProgress()->delete();

        // Clear caches
        $this->clearEnrollmentCaches($enrollment);
    }

    /**
     * Handle the Enrollment "restored" event.
     */
    public function restored(Enrollment $enrollment): void
    {
        Log::info('Enrollment restored', [
            'id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
        ]);

        // Increment course enrollment count
        if ($enrollment->payment_status === 'completed') {
            $enrollment->course->increment('students_count');
        }

        $this->clearEnrollmentCaches($enrollment);
    }

    /**
     * Clear enrollment-related caches
     */
    protected function clearEnrollmentCaches(Enrollment $enrollment): void
    {
        Cache::forget("enrollment_{$enrollment->id}");
        Cache::forget("user_{$enrollment->user_id}_enrollments");
        Cache::forget("user_{$enrollment->user_id}_courses");
        Cache::forget("course_{$enrollment->course_id}_enrollments");
        Cache::forget("course_{$enrollment->course_id}_analytics");

        // Clear tenant-specific caches
        if (tenant()) {
            Cache::forget("tenant_" . tenant()->id . "_enrollments");
        }
    }
}
