<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = [
        'user_id', 'course_id', 'price', 'paid_amount', 'payment_method',
        'payment_status', 'transaction_id', 'progress', 'enrolled_at', 'completed_at', 'metadata'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'progress' => 'integer',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function isCompleted()
    {
        return $this->progress >= 100;
    }
    /**
     * Scope for completed enrollments
     */
    public function scopeCompleted($query)
    {
        return $query->where('payment_status', 'completed');
    }
    public function calculateProgress()
    {
        $total = $this->course->lessons()->count();
        if ($total === 0) return 0;

        $completed = $this->lessonProgress()->where('is_completed', true)->count();
        return round(($completed / $total) * 100);
    }
}
