<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'order' => $this->order,
            'is_visible' => $this->is_visible,
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
            'lessons_count' => $this->when($this->relationLoaded('lessons'), $this->lessons->count()),
            'duration' => $this->when($this->relationLoaded('lessons'), $this->lessons->sum('duration')),
            'completed_lessons_count' => $this->when(
                $this->relationLoaded('lessons') && auth()->check(),
                $this->lessons->filter(fn($lesson) => auth()->user()->hasCompletedLesson($lesson))->count()
            ),
        ];
    }
}
