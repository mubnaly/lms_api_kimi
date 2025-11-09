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
        /* flush spatie cache */
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /* ---------------- permissions ---------------- */
        $permissions = [
            /* course */
            'view courses', 'create courses', 'edit courses', 'delete courses',
            'publish courses', 'approve courses',

            /* lesson / section */
            'create lessons',  'edit lessons',  'delete lessons',
            'create sections', 'edit sections', 'delete sections',

            /* enrollment */
            'enroll students', 'view enrollments', 'manage enrollments',

            /* reviews */
            'create reviews', 'edit reviews', 'delete reviews', 'approve reviews',

            /* coupons */
            'create coupons', 'edit coupons', 'delete coupons',

            /* users */
            'view users', 'create users', 'edit users', 'delete users', 'approve instructors',

            /* misc */
            'view analytics', 'view own analytics', 'manage categories', 'manage settings',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        /* ---------------- roles ---------------- */
        // Admin
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        if ($adminRole->wasRecentlyCreated) {   // only sync permissions the first time
            $adminRole->givePermissionTo(Permission::all());
        }

        // Instructor
        $instructorRole = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        if ($instructorRole->wasRecentlyCreated) {
            $instructorRole->givePermissionTo([
                'view courses', 'create courses', 'edit courses', 'delete courses',
                'create lessons', 'edit lessons', 'delete lessons',
                'create sections', 'edit sections', 'delete sections',
                'view enrollments',
                'create coupons', 'edit coupons', 'delete coupons',
                'view own analytics',
            ]);
        }
        // Student
        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        if ($studentRole->wasRecentlyCreated) {
            $studentRole->givePermissionTo([
                    'view courses', 'enroll students', 'create reviews', 'edit reviews',
                ]);
        }
        /* ---------------- users ---------------- */
        // Admin
        $admin = User::create([
            'name'              => 'Super Admin',
            'email'             => 'admin@lms.test',
            'password'          => Hash::make('password'),
            'avatar'            => null,
            'role'              => 'admin',
            'is_active'         => true,
            'is_verified'       => true,
            'metadata'          => [
                'bio'         => 'System Administrator',
                'created_by'  => 'system',
            ],
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // Approved Instructor
        $instructor = User::create([
            'name'              => 'Ahmed Hassan',
            'email'             => 'instructor@lms.test',
            'password'          => Hash::make('password'),
            'avatar'            => 'https://ui-avatars.com/api/?name=Ahmed+Hassan&background=10b981&color=fff',
            'role'              => 'instructor',
            'is_active'         => true,
            'is_verified'       => true,
            'metadata'          => [
                'bio'            => 'Expert Web-Dev instructor with 10 yrs experience',
                'expertise'      => ['PHP', 'Laravel', 'JavaScript', 'Vue.js'],
                'qualifications' => ['B.Sc Computer Science', 'Certified Laravel Dev'],
                'social_links'   => [
                    'linkedin' => 'https://linkedin.com/in/ahmed-hassan',
                    'github'   => 'https://github.com/ahmed-hassan',
                ],
                'application_status' => 'approved',
                'applied_at'         => now()->subDays(30),
                'reviewed_at'        => now()->subDays(29),
            ],
            'email_verified_at' => now(),
        ]);
        $instructor->assignRole('instructor');

        // Pending Instructor
        $pending = User::create([
            'name'        => 'Mohamed Ali',
            'email'       => 'pending@lms.test',
            'password'    => Hash::make('password'),
            'avatar'      => 'https://ui-avatars.com/api/?name=Mohamed+Ali&background=f59e0b&color=fff',
            'role'        => 'instructor',
            'is_active'   => true,
            'is_verified' => false,
            'metadata'    => [
                'bio'            => 'Passionate Data-Science instructor',
                'expertise'      => ['Python', 'Data Science', 'Machine Learning'],
                'qualifications' => ['M.Sc Data Science'],
                'application_status' => 'pending',
                'applied_at'         => now()->subDays(5),
            ],
        ]);
        $pending->assignRole('instructor');

        // Students
        $student1 = User::create([
            'name'        => 'Sara Ahmed',
            'email'       => 'student@lms.test',
            'password'    => Hash::make('password'),
            'avatar'      => 'https://ui-avatars.com/api/?name=Sara+Ahmed&background=3b82f6&color=fff',
            'role'        => 'student',
            'is_active'   => true,
            'is_verified' => true,
            'metadata'    => [
                'bio'        => 'Computer-science student',
                'interests'  => ['Web Dev', 'Mobile Apps'],
            ],
            'email_verified_at' => now(),
        ]);
        $student1->assignRole('student');

        $student2 = User::create([
            'name'        => 'Omar Ibrahim',
            'email'       => 'student2@lms.test',
            'password'    => Hash::make('password'),
            'avatar'      => 'https://ui-avatars.com/api/?name=Omar+Ibrahim&background=8b5cf6&color=fff',
            'role'        => 'student',
            'is_active'   => true,
            'is_verified' => true,
            'metadata'    => ['bio' => 'Aspiring full-stack developer'],
            'email_verified_at' => now(),
        ]);
        $student2->assignRole('student');

        /* ---------------- output ---------------- */
        $this->command->info('✅ Roles & permissions seeded successfully!');
        $this->command->line('');
        $this->command->table(
            ['Role', 'Login', 'Password'],
            [
                ['Super Admin', 'admin@lms.test', 'password'],
                ['Instructor (approved)', 'instructor@lms.test', 'password'],
                ['Instructor (pending)', 'pending@lms.test', 'password'],
                ['Student 1', 'student@lms.test', 'password'],
                ['Student 2', 'student2@lms.test', 'password'],
            ]
        );
    }
}
