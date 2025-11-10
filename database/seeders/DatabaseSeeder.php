<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\Coupon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->createTenantDatabase();
        $this->seedLandlord();
        $this->seedTenantData();
    }

    private function createTenantDatabase(): void
    {
        try {
            DB::statement('CREATE DATABASE IF NOT EXISTS tenant_demo');
            echo "✅ Database 'tenant_demo' created!\n";
        } catch (\Exception $e) {
            echo "⚠️  Could not create DB: {$e->getMessage()}\n";
        }
    }

    private function seedLandlord(): void
    {
        // RAW INSERT to bypass Tenant model's 'data' column
        DB::table('tenants')->insert([
            'id' => 'demo-tenant',
            'name' => 'Demo LMS Institution',
            'organization_name' => 'Demo Learning Academy',
            'email' => 'admin@demo-lms.com',
            'phone' => '+1234567890',
            'address' => '123 Education Street',
            'city' => 'Cairo',
            'country' => 'Egypt',
            'database' => 'tenant_demo',
            'domain' => 'demo.lms.local',
            'primary_color' => '#3b82f6',
            'secondary_color' => '#10b981',
            'is_active' => true,
            'subscription_ends_at' => now()->addYear(),
            'metadata' => json_encode([
                'max_students' => 1000,
                'max_instructors' => 50,
                'features' => ['courses', 'quizzes', 'certificates', 'live_classes'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Domain::create([
            'domain' => 'demo.lms.local',
            'tenant_id' => 'demo-tenant',
        ]);
    }

    private function seedTenantData(): void
    {
        // Switch to tenant DB
        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'tenant_demo');
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Run tenant migrations
        $this->command->call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations',
            '--force' => true,
        ]);

        // Seed tenant tables
        $this->seedUsers();
        $this->seedCategories();
        $this->seedCourses();
        $this->seedEnrollments();
        $this->seedCoupons();

        // Switch back
        Config::set('database.default', 'mysql');
        DB::purge('tenant');
    }

    private function seedUsers(): void
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@lms.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
            'is_verified' => true,
        ]);

        foreach (['Dr. Ahmed', 'Prof. Sarah', 'Eng. Karim'] as $name) {
            User::create([
                'name' => $name,
                'email' => strtolower(str_replace(' ', '', $name)) . '@lms.com',
                'password' => bcrypt('password123'),
                'role' => 'instructor',
                'is_active' => true,
                'is_verified' => true,
                'metadata' => ['specialization' => 'Computer Science'],
            ]);
        }

        foreach (['Ali', 'Fatima', 'Mohamed'] as $name) {
            User::create([
                'name' => $name . ' Student',
                'email' => strtolower($name) . '@lms.com',
                'password' => bcrypt('password123'),
                'role' => 'student',
                'is_active' => true,
                'is_verified' => true,
            ]);
        }
    }

    private function seedCategories(): void
    {
        $cats = [
            ['name' => 'Programming', 'icon' => '💻', 'parent_id' => null],
            ['name' => 'Web Dev', 'icon' => '🌐', 'parent_id' => 1],
            ['name' => 'Data Science', 'icon' => '📊', 'parent_id' => 1],
            ['name' => 'Design', 'icon' => '🎨', 'parent_id' => null],
        ];

        foreach ($cats as $cat) {
            Category::create([
                'name' => $cat['name'],
                'slug' => \Str::slug($cat['name']),
                'icon' => $cat['icon'],
                'description' => fake()->sentence(),
                'is_active' => true,
                'parent_id' => $cat['parent_id'],
            ]);
        }
    }

    private function seedCourses(): void
    {
        $instructors = User::role('instructor')->get();
        $categories = Category::all();

        $courseData = [
            [
                'title' => 'Laravel API Mastery',
                'subtitle' => 'Build scalable REST APIs',
                'description' => 'Master API development with Laravel',
                'requirements' => json_encode(['PHP basics', 'OOP concepts']),
                'outcomes' => json_encode(['Build APIs', 'Authentication', 'Testing']),
                'level' => 'intermediate',
                'price' => 299.99,
                'discount_price' => 199.99,
                'duration' => 480,
                'instructor_id' => $instructors->first()->id,
                'category_id' => $categories[1]->id,
            ],
            [
                'title' => 'Python Data Science',
                'subtitle' => 'Analytics & ML fundamentals',
                'description' => 'Learn pandas, numpy, matplotlib',
                'requirements' => json_encode(['Python basics']),
                'outcomes' => json_encode(['Data analysis', 'Visualization']),
                'level' => 'beginner',
                'price' => 249.99,
                'duration' => 360,
                'instructor_id' => $instructors->skip(1)->first()->id,
                'category_id' => $categories[2]->id,
            ],
        ];

        foreach ($courseData as $data) {
            $course = Course::create(array_merge($data, [
                'is_published' => true,
                'is_approved' => true,
                'status' => 'approved',
                'slug' => \Str::slug($data['title']),
                'language' => 'english',
                'metadata' => ['seeded' => true],
            ]));

            $this->seedCourseSections($course);
        }
    }

    private function seedCourseSections($course): void
    {
        foreach (['Introduction', 'Core Concepts', 'Advanced Topics'] as $index => $title) {
            $section = CourseSection::create([
                'course_id' => $course->id,
                'title' => $title,
                'order' => $index,
                'is_visible' => true,
            ]);

            $this->seedLessons($course, $section);
        }
    }

    private function seedLessons($course, $section): void
    {
        foreach (range(1, 3) as $i) {
            Lesson::create([
                'course_id' => $course->id,
                'section_id' => $section->id,
                'title' => "$section->title - Lesson $i",
                'slug' => \Str::slug("$section->title-lesson-$i"),
                'description' => fake()->sentence(),
                'type' => 'video',
                'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'video_platform' => 'youtube',
                'video_id' => 'dQw4w9WgXcQ',
                'duration' => 600,
                'order' => $i,
                'is_preview' => $section->order === 0 && $i === 1,
                'is_free' => $section->order === 0 && $i === 1,
                'is_visible' => true,
            ]);
        }
    }

    private function seedEnrollments(): void
    {
        $students = User::where('role', 'student')->get();
        $courses = Course::all();

        foreach ($students as $student) {
            foreach ($courses->random(rand(1, 2)) as $course) {
                $enrollment = Enrollment::create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'price' => $course->price,
                    'paid_amount' => $course->discount_price ?? $course->price,
                    'payment_status' => 'completed',
                    'payment_method' => 'credit_card',
                    'progress' => rand(20, 80),
                    'enrolled_at' => now()->subDays(rand(1, 30)),
                ]);

                if ($enrollment->progress > 60) {
                    Review::create([
                        'user_id' => $student->id,
                        'course_id' => $course->id,
                        'enrollment_id' => $enrollment->id,
                        'rating' => rand(4, 5),
                        'comment' => fake()->paragraph(),
                        'is_approved' => true,
                    ]);
                }
            }
        }
    }

    private function seedCoupons(): void
    {
        Coupon::create([
            'code' => 'WELCOME20',
            'type' => 'percentage',
            'value' => 20,
            'max_uses' => 100,
            'is_active' => true,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'instructor_id' => User::instructor()->first()->id,
        ]);
    }
}
