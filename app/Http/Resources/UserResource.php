<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar_url,
            'role' => $this->role,
            'phone' => $this->phone,
            'is_verified' => $this->is_verified,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toISOString(),

            // Instructor stats (if applicable)
            'instructor_stats' => $this->when($this->role === 'instructor', [
                'total_students' => $this->total_students,
                'total_revenue' => $this->total_revenue,
                'total_courses' => $this->courses()->published()->count(),
            ]),
        ];
    }
}
