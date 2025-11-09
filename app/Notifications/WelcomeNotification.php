<?php

namespace App\Notifications;

use App\Models\{Enrollment, User, Course};
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\{Notification, Messages\MailMessage};
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Welcome Notification
 */
class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to ' . config('app.name'))
            ->greeting("Welcome, {$this->user->name}!")
            ->line('Thank you for joining our learning platform.')
            ->line('Start exploring our courses and begin your learning journey.')
            ->action('Browse Courses', url('/courses'))
            ->line('Happy Learning!');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'Welcome to ' . config('app.name'),
            'user_id' => $this->user->id,
        ];
    }
}

/**
 * Enrollment Confirmation Notification
 */
class EnrollmentConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enrollment $enrollment) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject('Enrollment Confirmed: ' . $course->title)
            ->greeting("Hello, {$notifiable->name}!")
            ->line("You have successfully enrolled in: **{$course->title}**")
            ->line('Amount Paid: ' . format_price($this->enrollment->paid_amount))
            ->action('Start Learning', url("/courses/{$course->slug}"))
            ->line('Thank you for choosing our platform!');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'Enrollment confirmed',
            'course_id' => $this->enrollment->course_id,
            'enrollment_id' => $this->enrollment->id,
        ];
    }
}

/**
 * Payment Success Notification
 */
class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enrollment $enrollment) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Successful')
            ->greeting("Hello, {$notifiable->name}!")
            ->line('Your payment has been processed successfully.')
            ->line('Course: ' . $this->enrollment->course->title)
            ->line('Amount: ' . format_price($this->enrollment->paid_amount))
            ->line('Transaction ID: ' . $this->enrollment->transaction_id)
            ->action('View Enrollment', url("/enrollments/{$this->enrollment->id}"))
            ->line('Thank you for your payment!');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'Payment successful',
            'amount' => $this->enrollment->paid_amount,
            'enrollment_id' => $this->enrollment->id,
        ];
    }
}

/**
 * Course Completion Certificate Notification
 */
class CourseCompletionCertificate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enrollment $enrollment) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject('Congratulations! Course Completed: ' . $course->title)
            ->greeting("Congratulations, {$notifiable->name}! 🎉")
            ->line("You have successfully completed: **{$course->title}**")
            ->line('Your certificate is now available.')
            ->action('Download Certificate', url("/certificates/{$this->enrollment->id}"))
            ->line('Keep up the great work!');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'Course completed! Certificate available',
            'course_id' => $this->enrollment->course_id,
            'enrollment_id' => $this->enrollment->id,
        ];
    }
}

/**
 * Instructor Approval Notification
 */
class InstructorApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $instructor) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Instructor Application Approved!')
            ->greeting("Congratulations, {$this->instructor->name}! 🎉")
            ->line('Your instructor application has been approved!')
            ->line('You can now start creating and publishing courses.')
            ->action('Create Your First Course', url('/instructor/courses/create'))
            ->line('Welcome to our instructor community!');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'Instructor application approved',
            'instructor_id' => $this->instructor->id,
        ];
    }
}

/**
 * New Student Enrolled Notification (for instructors)
 */
class NewStudentEnrolledNotification extends Notification
{
    use Queueable;

    public function __construct(public Enrollment $enrollment) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'New student enrolled in your course',
            'student_name' => $this->enrollment->user->name,
            'course_id' => $this->enrollment->course_id,
            'course_title' => $this->enrollment->course->title,
        ];
    }
}

/**
 * Course Published Notification
 */
class CoursePublishedNotification extends Notification
{
    use Queueable;

    public function __construct(public Course $course) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Course Published: ' . $this->course->title)
            ->greeting("Hello, {$notifiable->name}!")
            ->line("Your course **{$this->course->title}** is now live!")
            ->line('Students can now enroll and start learning.')
            ->action('View Course', url("/courses/{$this->course->slug}"))
            ->line('Good luck with your course!');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'Course published',
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
        ];
    }
}

/**
 * New Instructor Application Notification (for admins)
 */
class NewInstructorApplicationNotification extends Notification
{
    use Queueable;

    public function __construct(public User $applicant) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Instructor Application')
            ->line('A new instructor application has been submitted.')
            ->line('Applicant: ' . $this->applicant->name)
            ->line('Email: ' . $this->applicant->email)
            ->action('Review Application', url("/admin/instructors/pending"))
            ->line('Please review and approve/reject the application.');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'New instructor application',
            'applicant_id' => $this->applicant->id,
            'applicant_name' => $this->applicant->name,
        ];
    }
}

/**
 * Payment Failure Notification (for admins)
 */
class PaymentFailureNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Enrollment $enrollment,
        public string $reason
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Failed - Action Required')
            ->line('A payment has failed.')
            ->line('User: ' . $this->enrollment->user->name)
            ->line('Course: ' . $this->enrollment->course->title)
            ->line('Reason: ' . $this->reason)
            ->action('View Details', url("/admin/enrollments/{$this->enrollment->id}"))
            ->line('Please investigate and take appropriate action.');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'Payment failed',
            'enrollment_id' => $this->enrollment->id,
            'reason' => $this->reason,
        ];
    }
}
