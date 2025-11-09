<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\{HasMedia, InteractsWithMedia};
use Laravel\Cashier\Billable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasRoles, InteractsWithMedia, Billable, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'role', 'phone',
        'is_active', 'is_verified', 'metadata'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'metadata' => 'array',
    ];

    // ────────────── Relationships ──────────────

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function wishlistCourses()
    {
        return $this->belongsToMany(Course::class, 'wishlist')
            ->withTimestamps();
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    // ────────────── Scopes ──────────────

    public function scopeInstructors($query)
    {
        return $query->where('role', 'instructor');
    }

    public function scopeStudents($query)
    {
        return $query->where('role', 'student');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('role', 'instructor')
            ->where('is_verified', false)
            ->whereJsonContains('metadata->application_status', 'pending');
    }

    // ────────────── Methods ──────────────

    public function hasCourse(Course $course): bool
    {
        return $this->enrollments()
            ->where('course_id', $course->id)
            ->where('payment_status', 'completed')
            ->exists();
    }

    public function hasCompletedLesson(Lesson $lesson): bool
    {
        return $this->lessonProgress()
            ->where('lesson_id', $lesson->id)
            ->where('is_completed', true)
            ->exists();
    }

    public function canAccessLesson(Lesson $lesson): bool
    {
        // Instructor can access own course lessons
        if ($this->id === $lesson->course->instructor_id) {
            return true;
        }

        // Preview lessons are public
        if ($lesson->is_preview) {
            return true;
        }

        // Check enrollment
        return $this->hasCourse($lesson->course);
    }

    // ────────────── Attributes ──────────────

    public function getTotalStudentsAttribute(): int
    {
        return Enrollment::whereIn('course_id', $this->courses()->pluck('id'))
            ->where('payment_status', 'completed')
            ->distinct('user_id')
            ->count('user_id');
    }

    public function getTotalRevenueAttribute(): float
    {
        return Enrollment::whereIn('course_id', $this->courses()->pluck('id'))
            ->where('payment_status', 'completed')
            ->sum('paid_amount');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?? $this->avatar;
    }

    public function getIsInstructorAttribute(): bool
    {
        return $this->role === 'instructor';
    }

    public function getIsStudentAttribute(): bool
    {
        return $this->role === 'student';
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->role === 'admin';
    }

    // ────────────── Media Collections ──────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
            ->maxFileSize(2 * 1024 * 1024); // 2MB
    }
}
