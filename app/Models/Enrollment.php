<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Enrollment extends Model
{
    protected $fillable = [
        'user_id', 'course_id', 'price', 'paid_amount', 'payment_method',
        'payment_status', 'transaction_id', 'progress', 'enrolled_at',
        'completed_at', 'metadata'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'progress' => 'integer',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::updated(function ($enrollment) {
            if ($enrollment->wasChanged('progress') && $enrollment->progress >= 100) {
                $enrollment->markAsCompleted();
            }
        });
    }

    // ────────────── Relationships ──────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // ────────────── Scopes ──────────────

    public function scopeCompleted($query)
    {
        return $query->where('payment_status', 'completed');
    }

    public function scopeActive($query)
    {
        return $query->where('payment_status', 'completed')
                     ->whereNull('completed_at');
    }

    public function scopeFinished($query)
    {
        return $query->where('payment_status', 'completed')
                     ->whereNotNull('completed_at');
    }

    // ────────────── Methods ──────────────

    public function isCompleted(): bool
    {
        return $this->progress >= 100 && $this->completed_at !== null;
    }

    public function isActive(): bool
    {
        return $this->payment_status === 'completed' && !$this->isCompleted();
    }

    public function calculateProgress(): int
    {
        $totalLessons = $this->course->lessons()->count();

        if ($totalLessons === 0) {
            return 0;
        }

        $completedLessons = $this->lessonProgress()
            ->where('is_completed', true)
            ->count();

        return min(100, round(($completedLessons / $totalLessons) * 100));
    }

    public function updateProgress(): void
    {
        $newProgress = $this->calculateProgress();

        $this->update(['progress' => $newProgress]);

        if ($newProgress >= 100 && !$this->completed_at) {
            $this->markAsCompleted();
        }
    }

    public function markAsCompleted(): void
    {
        if ($this->completed_at) {
            return; // Already completed
        }

        $this->update([
            'completed_at' => now(),
            'progress' => 100,
        ]);

        // Fire course completed event
        event(new \App\Events\CourseCompleted($this));
    }

    public function getTimeSpent(): int
    {
        return $this->lessonProgress()->sum('watched_seconds');
    }

    public function getCompletionRate(): float
    {
        $totalLessons = $this->course->lessons()->count();

        if ($totalLessons === 0) {
            return 0;
        }

        $completedLessons = $this->lessonProgress()
            ->where('is_completed', true)
            ->count();

        return round(($completedLessons / $totalLessons) * 100, 2);
    }

    public function getDaysEnrolled(): int
    {
        if (!$this->enrolled_at) {
            return 0;
        }

        return $this->enrolled_at->diffInDays(now());
    }

    public function getDaysToComplete(): ?int
    {
        if (!$this->completed_at || !$this->enrolled_at) {
            return null;
        }

        return $this->enrolled_at->diffInDays($this->completed_at);
    }

    public function canReview(): bool
    {
        return $this->isCompleted() && !$this->reviews()->exists();
    }

    public function hasReviewed(): bool
    {
        return $this->reviews()->exists();
    }

    public function getNextLesson(): ?Lesson
    {
        $completedLessonIds = $this->lessonProgress()
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->toArray();

        return $this->course->lessons()
            ->whereNotIn('id', $completedLessonIds)
            ->visible()
            ->ordered()
            ->first();
    }

    public function getCurrentLesson(): ?Lesson
    {
        $lastAccessed = $this->lessonProgress()
            ->latest('last_watched_at')
            ->first();

        return $lastAccessed?->lesson ?? $this->getNextLesson();
    }

    // ────────────── Attributes ──────────────

    public function getStatusAttribute(): string
    {
        if ($this->payment_status !== 'completed') {
            return $this->payment_status;
        }

        if ($this->isCompleted()) {
            return 'completed';
        }

        return 'in_progress';
    }

    public function getProgressPercentageAttribute(): string
    {
        return $this->progress . '%';
    }

    public function getFormattedEnrolledAtAttribute(): string
    {
        return $this->enrolled_at?->format('F d, Y') ?? 'N/A';
    }

    public function getFormattedCompletedAtAttribute(): ?string
    {
        return $this->completed_at?->format('F d, Y');
    }
}
