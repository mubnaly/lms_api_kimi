<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\{User, Category, Course, CourseSection, Lesson, Coupon, Enrollment, Review};

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('🚀 Starting Database Seeding...');
        $this->command->info('');

        // 1. Roles and Permissions
        $this->command->info('📋 Seeding Roles and Permissions...');
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Categories
        $this->command->info('📁 Seeding Categories...');
        $this->seedCategories();

        // 3. Courses
        $this->command->info('📚 Seeding Courses...');
        $this->seedCourses();

        // 4. Enrollments
        $this->command->info('🎓 Seeding Enrollments...');
        $this->seedEnrollments();

        // 5. Reviews
        $this->command->info('⭐ Seeding Reviews...');
        $this->seedReviews();

        $this->command->info('');
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('🔗 Test the API at: http://localhost:8000/api/v1');
        $this->command->info('📖 API Docs: http://localhost:8000/api/documentation');
    }

    protected function seedCategories(): void
    {
        $categories = [
            [
                'name' => 'Web Development',
                'slug' => 'web-development',
                'icon' => '💻',
                'description' => 'Learn modern web development technologies',
                'is_active' => true,
                'order' => 1,
                'children' => [
                    ['name' => 'Frontend', 'slug' => 'frontend', 'icon' => '🎨', 'order' => 1],
                    ['name' => 'Backend', 'slug' => 'backend', 'icon' => '⚙️', 'order' => 2],
                    ['name' => 'Full Stack', 'slug' => 'full-stack', 'icon' => '🔧', 'order' => 3],
                ],
            ],
            [
                'name' => 'Mobile Development',
                'slug' => 'mobile-development',
                'icon' => '📱',
                'description' => 'Build native and cross-platform mobile apps',
                'is_active' => true,
                'order' => 2,
                'children' => [
                    ['name' => 'Android', 'slug' => 'android', 'icon' => '🤖', 'order' => 1],
                    ['name' => 'iOS', 'slug' => 'ios', 'icon' => '🍎', 'order' => 2],
                    ['name' => 'Flutter', 'slug' => 'flutter', 'icon' => '🦋', 'order' => 3],
                ],
            ],
            [
                'name' => 'Data Science',
                'slug' => 'data-science',
                'icon' => '📊',
                'description' => 'Master data analysis and machine learning',
                'is_active' => true,
                'order' => 3,
            ],
            [
                'name' => 'Design',
                'slug' => 'design',
                'icon' => '🎨',
                'description' => 'UI/UX and graphic design courses',
                'is_active' => true,
                'order' => 4,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'icon' => '💼',
                'description' => 'Business and entrepreneurship skills',
                'is_active' => true,
                'order' => 5,
            ],
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $category = Category::create($categoryData);

            foreach ($children as $childData) {
                $childData['parent_id'] = $category->id;
                $childData['is_active'] = true;
                Category::create($childData);
            }
        }

        $this->command->info('   ✓ Created ' . Category::count() . ' categories');
    }

    protected function seedCourses(): void
    {
        $instructor = User::where('email', 'instructor@lms.test')->first();
        $backendCategory = Category::where('slug', 'backend')->first();
        $fullStackCategory = Category::where('slug', 'full-stack')->first();

        // Course 1: Complete Laravel Course
        $course1 = Course::create([
            'instructor_id' => $instructor->id,
            'category_id' => $backendCategory->id,
            'title' => 'Complete Laravel 11 Mastery',
            'slug' => 'complete-laravel-11-mastery',
            'subtitle' => 'Build Professional Web Applications from Scratch',
            'description' => 'Master Laravel 11 and build production-ready applications. Learn MVC, Authentication, API Development, Testing, and Deployment.',
            'requirements' => json_encode(['Basic PHP knowledge', 'HTML/CSS fundamentals', 'Basic understanding of databases']),
            'outcomes' => json_encode(['Build complete web applications', 'Master Laravel best practices', 'Deploy production apps', 'Create RESTful APIs']),
            'level' => 'intermediate',
            'language' => 'arabic',
            'price' => 499.00,
            'discount_price' => 299.00,
            'duration' => 0,
            'is_published' => true,
            'is_approved' => true,
            'status' => 'approved',
            'students_count' => 0,
            'rating' => 0.00,
            'reviews_count' => 0,
        ]);

        // Create sections and lessons for Course 1
        $section1 = CourseSection::create([
            'course_id' => $course1->id,
            'title' => 'Introduction to Laravel',
            'description' => 'Get started with Laravel framework',
            'order' => 1,
            'is_visible' => true,
        ]);

        Lesson::create([
            'course_id' => $course1->id,
            'section_id' => $section1->id,
            'title' => 'What is Laravel?',
            'slug' => 'what-is-laravel',
            'description' => 'Introduction to Laravel framework and its ecosystem',
            'type' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=ImtZ5yENzgE',
            'video_platform' => 'youtube',
            'video_id' => 'ImtZ5yENzgE',
            'duration' => 720,
            'is_preview' => true,
            'is_free' => true,
            'order' => 1,
            'is_visible' => true,
        ]);

        Lesson::create([
            'course_id' => $course1->id,
            'section_id' => $section1->id,
            'title' => 'Installing Laravel',
            'slug' => 'installing-laravel',
            'description' => 'Step by step Laravel installation guide',
            'type' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=MFh0Fd7BsjE',
            'video_platform' => 'youtube',
            'video_id' => 'MFh0Fd7BsjE',
            'duration' => 900,
            'is_preview' => true,
            'is_free' => false,
            'order' => 2,
            'is_visible' => true,
        ]);

        $section2 = CourseSection::create([
            'course_id' => $course1->id,
            'title' => 'Laravel Fundamentals',
            'description' => 'Core concepts of Laravel',
            'order' => 2,
            'is_visible' => true,
        ]);

        Lesson::create([
            'course_id' => $course1->id,
            'section_id' => $section2->id,
            'title' => 'Routing Basics',
            'slug' => 'routing-basics',
            'description' => 'Learn how to work with Laravel routes',
            'type' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=MYyJ4PuL4pY',
            'video_platform' => 'youtube',
            'video_id' => 'MYyJ4PuL4pY',
            'duration' => 1200,
            'is_preview' => false,
            'is_free' => false,
            'order' => 1,
            'is_visible' => true,
        ]);

        // Update course duration
        $course1->update(['duration' => $course1->lessons()->sum('duration')]);

        // Course 2: Flutter & Laravel API
        $course2 = Course::create([
            'instructor_id' => $instructor->id,
            'category_id' => $fullStackCategory->id,
            'title' => 'Flutter & Laravel Full Stack',
            'slug' => 'flutter-laravel-full-stack',
            'subtitle' => 'Build Mobile Apps with Flutter and Laravel Backend',
            'description' => 'Learn to build complete mobile applications with Flutter frontend and Laravel API backend.',
            'requirements' => json_encode(['Basic programming knowledge', 'Understanding of REST APIs']),
            'outcomes' => json_encode(['Build cross-platform mobile apps', 'Create REST APIs', 'Handle authentication']),
            'level' => 'advanced',
            'language' => 'arabic',
            'price' => 699.00,
            'discount_price' => null,
            'duration' => 0,
            'is_published' => true,
            'is_approved' => true,
            'status' => 'approved',
            'students_count' => 0,
            'rating' => 0.00,
            'reviews_count' => 0,
        ]);

        $section3 = CourseSection::create([
            'course_id' => $course2->id,
            'title' => 'Getting Started',
            'description' => 'Introduction to the course',
            'order' => 1,
            'is_visible' => true,
        ]);

        Lesson::create([
            'course_id' => $course2->id,
            'section_id' => $section3->id,
            'title' => 'Course Introduction',
            'slug' => 'course-introduction-flutter-laravel',
            'description' => 'What you will learn in this course',
            'type' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=VPvVD8t02U8',
            'video_platform' => 'youtube',
            'video_id' => 'VPvVD8t02U8',
            'duration' => 600,
            'is_preview' => true,
            'is_free' => true,
            'order' => 1,
            'is_visible' => true,
        ]);

        $course2->update(['duration' => $course2->lessons()->sum('duration')]);

        // Create coupons
        Coupon::create([
            'code' => 'WELCOME50',
            'type' => 'percentage',
            'value' => 50,
            'min_amount' => 100,
            'course_id' => null,
            'instructor_id' => $instructor->id,
            'max_uses' => 100,
            'uses_count' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'expires_at' => now()->addMonths(3),
        ]);

        Coupon::create([
            'code' => 'LARAVEL100',
            'type' => 'fixed',
            'value' => 100,
            'min_amount' => 200,
            'course_id' => $course1->id,
            'instructor_id' => $instructor->id,
            'max_uses' => 50,
            'uses_count' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'expires_at' => now()->addMonths(1),
        ]);

        $this->command->info('   ✓ Created ' . Course::count() . ' courses');
        $this->command->info('   ✓ Created ' . Lesson::count() . ' lessons');
        $this->command->info('   ✓ Created ' . Coupon::count() . ' coupons');
    }

    protected function seedEnrollments(): void
    {
        $student = User::where('email', 'student@lms.test')->first();
        $course = Course::where('slug', 'complete-laravel-11-mastery')->first();

        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'price' => $course->price,
            'paid_amount' => $course->discount_price ?? $course->price,
            'payment_method' => 'test',
            'payment_status' => 'completed',
            'transaction_id' => 'TEST_' . uniqid(),
            'progress' => 33,
            'enrolled_at' => now()->subDays(10),
            'metadata' => [
                'payment_gateway' => 'test',
                'test_mode' => true,
            ],
        ]);

        // Update course students count
        $course->increment('students_count');

        $this->command->info('   ✓ Created test enrollment');
    }

    protected function seedReviews(): void
    {
        $student = User::where('email', 'student@lms.test')->first();
        $enrollment = Enrollment::where('user_id', $student->id)->first();

        Review::create([
            'user_id' => $student->id,
            'course_id' => $enrollment->course_id,
            'enrollment_id' => $enrollment->id,
            'rating' => 5,
            'comment' => 'Excellent course! Very detailed and easy to follow. The instructor explains everything clearly.',
            'is_approved' => true,
        ]);

        // Update course rating
        $enrollment->course->updateRating();

        $this->command->info('   ✓ Created test review');
    }
}
