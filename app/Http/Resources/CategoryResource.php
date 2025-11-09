<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon_url,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'parent_id' => $this->parent_id,
            'order' => $this->order,
            'courses_count' => $this->when($this->relationLoaded('courses'), $this->courses->count()),
            'total_courses_count' => $this->getTotalCoursesCount(),
            'has_children' => $this->hasChildren(),
            'breadcrumb' => $this->when($request->routeIs('api.categories.show'), $this->getBreadcrumb()),
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
