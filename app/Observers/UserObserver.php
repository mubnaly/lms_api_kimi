<?php

namespace App\Observers;

use App\Models\User;
use App\Events\{UserRegistered, UserVerified, InstructorApproved, UserProfileUpdated};
use Illuminate\Support\Facades\{Cache, Log};
use App\Notifications\{WelcomeNotification, InstructorApprovalNotification};

class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        // Set default role if not provided
        if (empty($user->role)) {
            $user->role = 'student';
        }

        // Set default status
        if (!isset($user->is_active)) {
            $user->is_active = true;
        }

        if (!isset($user->is_verified)) {
            $user->is_verified = false;
        }

        // Initialize metadata if empty
        if (empty($user->metadata)) {
            $user->metadata = [
                'registered_at' => now()->toISOString(),
                'registration_ip' => request()->ip(),
                'tenant_id' => tenant()?->id,
            ];
        }

        Log::info('Creating user', [
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        Log::info('User created', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        event(new UserRegistered($user));

        // Send welcome notification
        $user->notify(new WelcomeNotification($user));

        // Clear users cache
        Cache::forget('users_list');
        Cache::forget("users_role_{$user->role}");
    }

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        $changes = $user->getDirty();

        // Check if user is being verified
        if (isset($changes['is_verified']) && $changes['is_verified'] && !$user->getOriginal('is_verified')) {
            Log::info('User being verified', [
                'id' => $user->id,
                'email' => $user->email,
            ]);

            // Update metadata
            $metadata = $user->metadata ?? [];
            $metadata['verified_at'] = now()->toISOString();
            $user->metadata = $metadata;
        }

        // Check if instructor is being approved
        if ($user->role === 'instructor' && isset($changes['is_verified']) && $changes['is_verified']) {
            Log::info('Instructor being approved', [
                'id' => $user->id,
                'email' => $user->email,
            ]);

            // Update metadata
            $metadata = $user->metadata ?? [];
            $metadata['application_status'] = 'approved';
            $metadata['approved_at'] = now()->toISOString();
            $user->metadata = $metadata;
        }

        // Check if role is changing
        if (isset($changes['role']) && $changes['role'] !== $user->getOriginal('role')) {
            Log::info('User role changing', [
                'id' => $user->id,
                'from' => $user->getOriginal('role'),
                'to' => $changes['role'],
            ]);
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $changes = $user->getChanges();

        Log::info('User updated', [
            'id' => $user->id,
            'changes' => array_keys($changes),
        ]);

        event(new UserProfileUpdated($user, $changes));

        // Clear user-related caches
        $this->clearUserCaches($user);

        // Send notification if verified
        if (isset($changes['is_verified']) && $user->is_verified) {
            event(new UserVerified($user));
        }

        // Send notification if instructor approved
        if ($user->role === 'instructor' && isset($changes['is_verified']) && $user->is_verified) {
            event(new InstructorApproved($user));
            $user->notify(new InstructorApprovalNotification($user));
        }
    }

    /**
     * Handle the User "deleting" event.
     */
    public function deleting(User $user): void
    {
        Log::warning('User being deleted', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        // Check if user has enrollments
        $enrollmentCount = $user->enrollments()->count();
        if ($enrollmentCount > 0) {
            Log::warning('Deleting user with enrollments', [
                'user_id' => $user->id,
                'enrollment_count' => $enrollmentCount,
            ]);
        }

        // Check if instructor has courses
        if ($user->role === 'instructor') {
            $courseCount = $user->courses()->count();
            if ($courseCount > 0) {
                Log::warning('Deleting instructor with courses', [
                    'user_id' => $user->id,
                    'course_count' => $courseCount,
                ]);
            }
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        Log::warning('User deleted', [
            'id' => $user->id,
            'email' => $user->email,
        ]);

        // Clear user-related caches
        $this->clearUserCaches($user);

        // Clear avatar media
        $user->clearMediaCollection('avatar');

        // Revoke all tokens
        $user->tokens()->delete();
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        Log::info('User restored', [
            'id' => $user->id,
            'email' => $user->email,
        ]);

        $this->clearUserCaches($user);
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        Log::critical('User force deleted', [
            'id' => $user->id,
            'email' => $user->email,
        ]);

        $this->clearUserCaches($user);
    }

    /**
     * Clear user-related caches
     */
    protected function clearUserCaches(User $user): void
    {
        Cache::forget("user_{$user->id}");
        Cache::forget("user_{$user->id}_profile");
        Cache::forget("user_{$user->id}_enrollments");
        Cache::forget("user_{$user->id}_courses");
        Cache::forget('users_list');
        Cache::forget("users_role_{$user->role}");

        // Clear instructor-specific caches
        if ($user->role === 'instructor') {
            Cache::forget("instructor_{$user->id}_courses");
            Cache::forget("instructor_{$user->id}_analytics");
            Cache::forget('instructors_list');
        }

        // Clear tenant-specific caches
        if (tenant()) {
            Cache::forget("tenant_" . tenant()->id . "_users");
        }
    }
}
