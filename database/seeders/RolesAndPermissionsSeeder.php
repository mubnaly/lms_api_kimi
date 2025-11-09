<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ════════════════════════════════════════════════
        // Create Permissions
        // ════════════════════════════════════════════════

        $permissions = [
            // Course Management
            'view courses',
            'create courses',
            'edit courses',
            'delete courses',
            'publish courses',
            'approve courses',

            // Lesson Management
            'create lessons',
            'edit lessons',
            'delete lessons',

            // Section Management
            'create sections',
            'edit sections',
            'delete sections',

            // Enrollment Management
            'enroll students',
            'view enrollments',
            'manage enrollments',

            // Review Management
            'create reviews',
            'edit reviews',
            'delete reviews',
            'approve reviews',

            // Coupon Management
            'create coupons',
            'edit coupons',
            'delete coupons',

            // User Management
            'view users',
            'create users',
            'edit users',
            'delete users',
            'approve instructors',

            // Analytics
            'view analytics',
            'view own analytics',

            // Category Management
            'manage categories',

            // System Settings
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // ════════════════════════════════════════════════
        // Create Roles and Assign Permissions
        // ════════════════════════════════════════════════

        // Admin Role - Full Access
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Instructor Role
        $instructorRole = Role::create(['name' => 'instructor']);
        $instructorRole->givePermissionTo([
            'view courses',
            'create courses',
            'edit courses',
            'delete courses',
            'create lessons',
            'edit lessons',
            'delete lessons',
            'create sections',
            'edit sections',
            'delete sections',
            'view enrollments',
            'create coupons',
            'edit coupons',
            'delete coupons',
            'view own analytics',
        ]);

        // Student Role
        $studentRole = Role::create(['name' => 'student']);
        $studentRole->givePermissionTo([
            'view courses',
            'enroll students',
            'create reviews',
            'edit reviews',
        ]);

        // ════════════════════════════════════════════════
        // Create Default Users
        // ════════════════════════════════════════════════

        // Super Admin
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@lms.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
            'is_verified' => true,
            'phone' => '+201000000001',
            'metadata' => [
                'created_by' => 'system',
                'bio' => 'System Administrator',
            ],
        ]);
        $admin->assignRole('admin');

        // Verified Instructor
        $instructor = User::create([
            'name' => 'Ahmed Hassan',
            'email' => 'instructor@lms.test',
            'password' => Hash::make('password'),
            'role' => 'instructor',
            'is_active' => true,
            'is_verified' => true,
            'phone' => '+201000000002',
            'metadata' => [
                'bio' => 'Expert in Web Development with 10 years of experience',
                'expertise' => ['PHP', 'Laravel', 'JavaScript', 'Vue.js'],
                'qualifications' => [
                    'Bachelor of Computer Science',
                    'Certified Laravel Developer',
                ],
                'social_links' => [
                    'linkedin' => 'https://linkedin.com/in/ahmed-hassan',
                    'github' => 'https://github.com/ahmed-hassan',
                ],
                'application_status' => 'approved',
                'applied_at' => now()->subDays(30)->toISOString(),
                'reviewed_at' => now()->subDays(29)->toISOString(),
            ],
        ]);
        $instructor->assignRole('instructor');

        // Pending Instructor (for testing approval flow)
        $pendingInstructor = User::create([
            'name' => 'Mohamed Ali',
            'email' => 'pending@lms.test',
            'password' => Hash::make('password'),
            'role' => 'instructor',
            'is_active' => true,
            'is_verified' => false,
            'phone' => '+201000000003',
            'metadata' => [
                'bio' => 'Passionate about teaching Data Science and Machine Learning',
                'expertise' => ['Python', 'Data Science', 'Machine Learning'],
                'qualifications' => [
                    'Master in Data Science',
                ],
                'application_status' => 'pending',
                'applied_at' => now()->subDays(5)->toISOString(),
            ],
        ]);
        $pendingInstructor->assignRole('instructor');

        // Student Users
        $student1 = User::create([
            'name' => 'Sara Ahmed',
            'email' => 'student@lms.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'is_active' => true,
            'is_verified' => true,
            'phone' => '+201000000004',
            'metadata' => [
                'bio' => 'Computer Science Student',
                'interests' => ['Web Development', 'Mobile Apps'],
            ],
        ]);
        $student1->assignRole('student');

        $student2 = User::create([
            'name' => 'Omar Ibrahim',
            'email' => 'student2@lms.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'is_active' => true,
            'is_verified' => true,
            'phone' => '+201000000005',
            'metadata' => [
                'bio' => 'Aspiring Full Stack Developer',
            ],
        ]);
        $student2->assignRole('student');

        $this->command->info('✅ Roles and Permissions seeded successfully!');
        $this->command->info('');
        $this->command->info('🔑 Default Users Created:');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('👑 Admin: admin@lms.test / password');
        $this->command->info('👨‍🏫 Instructor: instructor@lms.test / password');
        $this->command->info('⏳ Pending Instructor: pending@lms.test / password');
        $this->command->info('👨‍🎓 Student 1: student@lms.test / password');
        $this->command->info('👨‍🎓 Student 2: student2@lms.test / password');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
