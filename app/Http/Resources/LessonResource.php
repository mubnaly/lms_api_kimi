<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrollment = auth()->user()?->enrollments()
            ->where('course_id', $this->course_id)
            ->where('payment_status', 'completed')
            ->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'content' => $this->when(
                $enrollment || $this->is_preview,
                $this->content
            ),
            'video_url' => $this->when(
                ($enrollment || $this->is_preview) && $this->type === 'video',
                $this->video_url
            ),
            'video_embed_url' => $this->when(
                ($enrollment || $this->is_preview) && $this->type === 'video',
                $this->video_embed_url
            ),
            'video_platform' => $this->video_platform,
            'video_id' => $this->video_id,
            'duration' => $this->duration,
            'is_preview' => $this->is_preview,
            'is_free' => $this->is_free,
            'order' => $this->order,
            'is_visible' => $this->is_visible,
            'is_completed' => $this->when(
                auth()->check(),
                auth()->user()->lessonProgress()
                    ->where('lesson_id', $this->id)
                    ->where('is_completed', true)
                    ->exists()
            ),
            'watched_seconds' => $this->when(
                auth()->check(),
                auth()->user()->lessonProgress()
                    ->where('lesson_id', $this->id)
                    ->first()
                    ?->watched_seconds ?? 0
            ),
            'document_url' => $this->when(
                $this->type === 'document',
                $this->getFirstMediaUrl('documents')
            ),
            'next_lesson_id' => $this->when($this->relationLoaded('nextLesson'), $this->nextLesson?->id),
            'previous_lesson_id' => $this->when($this->relationLoaded('previousLesson'), $this->previousLesson?->id),
        ];
    }
}
