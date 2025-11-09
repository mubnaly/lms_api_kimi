<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    protected $fillable = [
        'user_id', 'enrollment_id', 'lesson_id', 'progress',
        'watched_seconds', 'is_completed', 'last_watched_at'
    ];

    protected $casts = [
        'progress' => 'integer',
        'watched_seconds' => 'integer',
        'is_completed' => 'boolean',
        'last_watched_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function markAsCompleted()
    {
        $this->update([
            'is_completed' => true,
            'progress' => 100,
            'watched_seconds' => $this->lesson->duration,
            'last_watched_at' => now(),
        ]);
    }
}
