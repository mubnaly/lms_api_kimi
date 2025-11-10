<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Log, Cache};

class InstructorOnboardingService
{
    /**
     * Setup instructor dashboard after approval
     */
    public function setupDashboard(User $instructor): void
    {
        DB::transaction(function () use ($instructor) {
            // Create welcome course template
            $this->createWelcomeCourse($instructor);

            // Setup instructor preferences
            $this->initializePreferences($instructor);

            // Create sample coupon
            $this->createSampleCoupon($instructor);

            // Clear cache
            $this->clearInstructorCache($instructor);

            Log::info('Instructor dashboard setup completed', [
                'instructor_id' => $instructor->id,
            ]);
        });
    }

    /**
     * Create welcome course template
     */
    protected function createWelcomeCourse(User $instructor): void
    {
        $welcomeCourse = $instructor->courses()->create([
            'title' => 'Welcome to Teaching on ' . config('app.name'),
            'slug' => 'welcome-' . $instructor->id,
            'subtitle' => 'Get started with your instructor journey',
            'description' => 'This is a sample course to help you understand how to create and manage courses.',
            'level' => 'beginner',
            'language' => 'arabic',
            'price' => 0,
            'category_id' => 1, // Default category
            'is_published' => false,
            'is_approved' => false,
            'status' => 'draft',
            'metadata' => [
                'is_template' => true,
                'created_by_system' => true,
            ],
        ]);

        // Create sample section
        $section = $welcomeCourse->sections()->create([
            'title' => 'Getting Started',
            'description' => 'Learn the basics of course creation',
            'order' => 1,
            'is_visible' => true,
        ]);

        // Create sample lesson
        $section->lessons()->create([
            'course_id' => $welcomeCourse->id,
            'title' => 'Welcome to the Platform',
            'slug' => 'welcome-lesson-' . $instructor->id,
            'description' => 'Introduction to the instructor dashboard',
            'type' => 'text',
            'content' => $this->getWelcomeContent(),
            'duration' => 300,
            'is_preview' => true,
            'is_free' => true,
            'order' => 1,
            'is_visible' => true,
        ]);
    }

    /**
     * Initialize instructor preferences
     */
    protected function initializePreferences(User $instructor): void
    {
        $instructor->update([
            'metadata' => array_merge($instructor->metadata ?? [], [
                'preferences' => [
                    'notifications' => [
                        'new_enrollment' => true,
                        'new_review' => true,
                        'course_approved' => true,
                    ],
                    'dashboard_layout' => 'default',
                    'onboarding_completed' => false,
                    'onboarding_steps' => [
                        'profile_completed' => true,
                        'first_course_created' => false,
                        'first_lesson_created' => false,
                        'first_student_enrolled' => false,
                    ],
                ],
                'statistics' => [
                    'total_courses' => 0,
                    'total_students' => 0,
                    'total_revenue' => 0,
                    'average_rating' => 0,
                ],
            ])
        ]);
    }

    /**
     * Create sample coupon
     */
    protected function createSampleCoupon(User $instructor): void
    {
        \App\Models\Coupon::create([
            'code' => 'WELCOME' . strtoupper(substr($instructor->name, 0, 3)),
            'type' => 'percentage',
            'value' => 10,
            'instructor_id' => $instructor->id,
            'course_id' => null, // Applicable to all instructor's courses
            'max_uses' => 50,
            'uses_count' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'expires_at' => now()->addMonths(3),
        ]);
    }

    /**
     * Clear instructor cache
     */
    protected function clearInstructorCache(User $instructor): void
    {
        Cache::forget("instructor_{$instructor->id}_courses");
        Cache::forget("instructor_{$instructor->id}_analytics");
        Cache::forget('instructors_list');
    }

    /**
     * Get welcome content
     */
    protected function getWelcomeContent(): string
    {
        return <<<'HTML'
<h2>Welcome to Your Instructor Dashboard!</h2>

<p>Congratulations on becoming an instructor! Here's what you can do:</p>

<h3>Create Your First Course</h3>
<ul>
    <li>Navigate to "Courses" → "Create New Course"</li>
    <li>Fill in course details (title, description, price)</li>
    <li>Add sections to organize your content</li>
    <li>Upload lessons (video, documents, quizzes)</li>
</ul>

<h3>Manage Your Content</h3>
<ul>
    <li>Edit courses anytime</li>
    <li>Track student progress</li>
    <li>Respond to reviews</li>
    <li>View analytics and earnings</li>
</ul>

<h3>Best Practices</h3>
<ul>
    <li>Create engaging course descriptions</li>
    <li>Use high-quality video content</li>
    <li>Respond to student questions promptly</li>
    <li>Keep your courses updated</li>
</ul>

<p><strong>Need help?</strong> Contact our support team at support@lms.test</p>
HTML;
    }

    /**
     * Update onboarding progress
     */
    public function updateOnboardingProgress(User $instructor, string $step): void
    {
        $metadata = $instructor->metadata ?? [];
        $preferences = $metadata['preferences'] ?? [];
        $onboardingSteps = $preferences['onboarding_steps'] ?? [];

        $onboardingSteps[$step] = true;

        // Check if all steps completed
        $allCompleted = collect($onboardingSteps)->every(fn($value) => $value === true);

        if ($allCompleted) {
            $preferences['onboarding_completed'] = true;
        }

        $preferences['onboarding_steps'] = $onboardingSteps;
        $metadata['preferences'] = $preferences;

        $instructor->update(['metadata' => $metadata]);

        Cache::forget("instructor_{$instructor->id}_profile");
    }

    /**
     * Get onboarding progress
     */
    public function getOnboardingProgress(User $instructor): array
    {
        $preferences = $instructor->metadata['preferences'] ?? [];
        $steps = $preferences['onboarding_steps'] ?? [];

        return [
            'completed' => $preferences['onboarding_completed'] ?? false,
            'steps' => $steps,
            'progress_percentage' => $this->calculateProgress($steps),
        ];
    }

    /**
     * Calculate onboarding progress percentage
     */
    protected function calculateProgress(array $steps): int
    {
        if (empty($steps)) {
            return 0;
        }

        $completed = collect($steps)->filter(fn($value) => $value === true)->count();
        $total = count($steps);

        return round(($completed / $total) * 100);
    }

    /**
     * Send welcome email
     */
    public function sendWelcomeEmail(User $instructor): void
    {
        $instructor->notify(new \App\Notifications\InstructorApprovalNotification($instructor));
    }
}
