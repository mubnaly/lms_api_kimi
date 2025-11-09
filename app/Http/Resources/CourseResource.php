<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'outcomes' => $this->outcomes,
            'level' => $this->level,
            'language' => $this->language,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'final_price' => $this->final_price,
            'is_free' => $this->is_free,
            'duration' => $this->duration,
            'total_duration' => $this->total_duration,
            'total_lessons' => $this->total_lessons,
            'students_count' => $this->students_count,
            'rating' => round($this->rating, 1),
            'reviews_count' => $this->reviews_count,
            'thumbnail' => $this->thumbnail_url,
            'is_published' => $this->is_published,
            'is_approved' => $this->is_approved,
            'status' => $this->status,

            'category' => new CategoryResource($this->whenLoaded('category')),
            'instructor' => new UserResource($this->whenLoaded('instructor')),
            'sections' => CourseSectionResource::collection($this->whenLoaded('sections')),

            'is_enrolled' => $this->when(auth()->check(), fn() => auth()->user()->hasCourse($this->resource)),
            'progress' => $this->when(auth()->check(), fn() =>
                optional(auth()->user()->enrollments()->where('course_id', $this->id)->first())->progress ?? 0
            ),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
