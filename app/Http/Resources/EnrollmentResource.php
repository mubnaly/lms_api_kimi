<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'course' => new CourseResource($this->whenLoaded('course')),
            'user' => new UserResource($this->whenLoaded('user')),
            'price' => $this->price,
            'paid_amount' => $this->paid_amount,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'progress' => $this->progress,
            'enrolled_at' => $this->enrolled_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'is_completed' => $this->isCompleted(),
            'total_lessons' => $this->course->total_lessons,
            'completed_lessons' => $this->lessonProgress()->where('is_completed', true)->count(),
            'last_accessed_lesson' => $this->lessonProgress()
                ->latest('last_watched_at')
                ->first()?->lesson_id,
        ];
    }
}
