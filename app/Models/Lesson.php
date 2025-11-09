<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\{HasMedia, InteractsWithMedia};
use Illuminate\Support\Str;

class Lesson extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'course_id', 'section_id', 'title', 'slug', 'description', 'type',
        'content', 'video_url', 'video_platform', 'video_id', 'duration',
        'is_preview', 'is_free', 'order', 'is_visible', 'metadata'
    ];

    protected $casts = [
        'is_preview' => 'boolean',
        'is_free' => 'boolean',
        'is_visible' => 'boolean',
        'metadata' => 'array',
        'duration' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($lesson) {
            if (empty($lesson->slug)) {
                $lesson->slug = Str::slug($lesson->title);
            }
        });
    }

    // ────────────── Relationships ──────────────

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    // ────────────── Scopes ──────────────

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopePreview($query)
    {
        return $query->where('is_preview', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ────────────── Attributes ──────────────

    public function getVideoEmbedUrlAttribute(): ?string
    {
        if (!$this->video_platform || !$this->video_id) {
            return $this->video_url;
        }

        return match($this->video_platform) {
            'youtube' => "https://www.youtube.com/embed/{$this->video_id}",
            'vimeo' => "https://player.vimeo.com/video/{$this->video_id}",
            'dailymotion' => "https://www.dailymotion.com/embed/video/{$this->video_id}",
            default => $this->video_url,
        };
    }

    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getIsVideoAttribute(): bool
    {
        return $this->type === 'video';
    }

    public function getIsDocumentAttribute(): bool
    {
        return $this->type === 'document';
    }

    public function getDocumentUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('documents');
    }

    // ────────────── Methods ──────────────

    public function getNextLesson(): ?self
    {
        return $this->section->lessons()
            ->where('order', '>', $this->order)
            ->visible()
            ->ordered()
            ->first();
    }

    public function getPreviousLesson(): ?self
    {
        return $this->section->lessons()
            ->where('order', '<', $this->order)
            ->visible()
            ->orderByDesc('order')
            ->first();
    }

    public function isAccessibleBy(?User $user): bool
    {
        if (!$user) {
            return $this->is_preview;
        }

        // Instructor can access own course lessons
        if ($user->id === $this->course->instructor_id) {
            return true;
        }

        // Preview lessons are public
        if ($this->is_preview) {
            return true;
        }

        // Check enrollment
        return $user->hasCourse($this->course);
    }

    // ────────────── Media Collections ──────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ])
            ->maxFileSize(10 * 1024 * 1024); // 10MB
    }
}
