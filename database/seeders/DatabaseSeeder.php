<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\{User, Category, Course, CourseSection, Lesson, Coupon};

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@lms.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_verified' => true,
        ]);
        $admin->assignRole('admin');

        // Create instructor
        $instructor = User::create([
            'name' => 'Test Instructor',
            'email' => 'instructor@lms.test',
            'password' => Hash::make('password'),
            'role' => 'instructor',
            'is_verified' => false,
            'metadata' => [
                'bio' => 'Expert in web development',
                'expertise' => ['PHP', 'Laravel', 'JavaScript'],
            ],
        ]);
        $instructor->assignRole('instructor');

        // Create categories
        $categories = [
            ['name' => 'Programming', 'slug' => 'programming', 'icon' => '💻'],
            ['name' => 'Design', 'slug' => 'design', 'icon' => '🎨'],
            ['name' => 'Business', 'slug' => 'business', 'icon' => '💼'],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }

        // Create sample course
        $course = Course::create([
            'instructor_id' => $instructor->id,
            'category_id' => 1,
            'title' => 'Complete Laravel Course',
            'subtitle' => 'Master Laravel from scratch',
            'description' => 'Learn Laravel step by step',
            'requirements' => ['Basic PHP knowledge', 'HTML/CSS'],
            'outcomes' => ['Build web apps', 'Master API development'],
            'level' => 'beginner',
            'language' => 'arabic',
            'price' => 199.00,
            'duration' => 0,
            'status' => 'draft',
        ]);

        // Create sections and lessons
        $section = CourseSection::create([
            'course_id' => $course->id,
            'title' => 'Introduction',
            'order' => 1,
        ]);

        Lesson::create([
            'course_id' => $course->id,
            'section_id' => $section->id,
            'title' => 'What is Laravel?',
            'type' => 'video',
            'video_url' => 'https://www.youtube.com/embed/Ab_Button',
            'video_platform' => 'youtube',
            'video_id' => 'Ab_Button',
            'duration' => 600,
            'is_preview' => true,
            'order' => 1,
        ]);

        // Create coupon
        Coupon::create([
            'code' => 'WELCOME20',
            'type' => 'percentage',
            'value' => 20,
            'course_id' => $course->id,
            'instructor_id' => $instructor->id,
            'max_uses' => 100,
            'is_active' => true,
        ]);
    }
}
