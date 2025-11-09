<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\{HasMedia, InteractsWithMedia};
use Laravel\Cashier\Billable;
use Illuminate\Support\Str;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasRoles, InteractsWithMedia, Billable;

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'role',
        'is_active', 'is_verified', 'metadata', 'phone'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'metadata' => 'array',
    ];

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
        return $this->belongsToMany(Course::class, 'wishlist');
    }

    public function hasCourse(Course $course): bool
    {
        return $this->enrollments()->where('course_id', $course->id)->exists();
    }

    public function hasCompletedLesson(Lesson $lesson): bool
    {
        return $this->lessonProgress()
            ->where('lesson_id', $lesson->id)
            ->where('is_completed', true)
            ->exists();
    }
    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function getTotalStudentsAttribute(): int
    {
        return Enrollment::whereIn('course_id', $this->courses()->pluck('id'))->count();
    }

    public function getTotalRevenueAttribute(): float
    {
        return Enrollment::whereIn('course_id', $this->courses()->pluck('id'))
            ->where('payment_status', 'completed')
            ->sum('paid_amount');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?? $this->avatar;
    }

    public function scopeInstructors($query)
    {
        return $query->where('role', 'instructor');
    }

    public function scopeStudents($query)
    {
        return $query->where('role', 'student')->orWhereNull('role');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
