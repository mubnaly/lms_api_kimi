<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\{HasMedia, InteractsWithMedia};

class Lesson extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'course_id', 'section_id', 'title', 'description', 'type',
        'content', 'video_url', 'duration', 'video_platform', 'video_id',
        'is_preview', 'is_free', 'order', 'is_visible', 'metadata'
    ];

    protected $casts = [
        'is_preview' => 'boolean',
        'is_free' => 'boolean',
        'is_visible' => 'boolean',
        'metadata' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function getVideoEmbedUrlAttribute()
    {
        return match($this->video_platform) {
            'youtube' => "https://www.youtube.com/embed/{$this->video_id}",
            'vimeo' => "https://player.vimeo.com/video/{$this->video_id}",
            'dailymotion' => "https://www.dailymotion.com/embed/video/{$this->video_id}",
            default => $this->video_url,
        };
    }
    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }
    public function scopePreview($query)
    {
        return $query->where('is_preview', true);
    }
}
