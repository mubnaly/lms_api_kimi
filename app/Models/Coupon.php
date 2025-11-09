<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_amount', 'max_uses', 'uses_count',
        'is_active', 'starts_at', 'expires_at', 'course_id', 'instructor_id'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ────────────── Relationships ──────────────

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    // ────────────── Scopes ──────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')
                  ->orWhereRaw('uses_count < max_uses');
            });
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->where(function ($q) use ($courseId) {
            $q->whereNull('course_id')
              ->orWhere('course_id', $courseId);
        });
    }

    // ────────────── Methods ──────────────

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function canBeAppliedTo(Course $course, float $amount): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->course_id && $this->course_id !== $course->id) {
            return false;
        }

        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'percentage') {
            $discount = ($amount * $this->value) / 100;
        } else {
            $discount = $this->value;
        }

        return min($discount, $amount); // Can't discount more than total
    }

    public function incrementUsage(): void
    {
        $this->increment('uses_count');
    }

    public function getUsagePercentageAttribute(): float
    {
        if (!$this->max_uses) {
            return 0;
        }

        return ($this->uses_count / $this->max_uses) * 100;
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if (!$this->max_uses) {
            return null;
        }

        return max(0, $this->max_uses - $this->uses_count);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsFullyUsedAttribute(): bool
    {
        return $this->max_uses && $this->uses_count >= $this->max_uses;
    }
}
