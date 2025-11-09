<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // ═══════════════ Payment Events ═══════════════
        \App\Events\PaymentCompleted::class => [
            \App\Listeners\SendPaymentSuccessNotification::class,
        ],

        \App\Events\PaymentFailed::class => [
            \App\Listeners\LogPaymentFailure::class,
        ],

        // ═══════════════ Enrollment Events ═══════════════
        \App\Events\EnrollmentCompleted::class => [
            \App\Listeners\SendEnrollmentConfirmation::class,
        ],

        \App\Events\CourseCompleted::class => [
            \App\Listeners\SendCompletionCertificate::class,
        ],

        // ═══════════════ Course Events ═══════════════
        \App\Events\CoursePublished::class => [
            \App\Listeners\NotifyCoursePublished::class,
        ],

        // ═══════════════ User Events ═══════════════
        \App\Events\UserRegistered::class => [
            \App\Listeners\SendWelcomeEmail::class,
        ],

        \App\Events\InstructorApproved::class => [
            \App\Listeners\NotifyInstructorApproval::class,
        ],

        \App\Events\InstructorApplicationSubmitted::class => [
            \App\Listeners\NotifyAdminOfInstructorApplication::class,
        ],

        // ═══════════════ Laravel Default Events ═══════════════
        \Illuminate\Auth\Events\Registered::class => [
            \Illuminate\Auth\Listeners\SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * The model observers to register.
     *
     * @var array
     */
    protected $observers = [
        \App\Models\Course::class => [\App\Observers\CourseObserver::class],
        \App\Models\Enrollment::class => [\App\Observers\EnrollmentObserver::class],
        \App\Models\User::class => [\App\Observers\UserObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
