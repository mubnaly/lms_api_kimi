<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Laravel\Sanctum\PersonalAccessToken;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('instructor') || $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'description' => 'required|string',
            'requirements' => 'nullable|array',
            'outcomes' => 'nullable|array',
            'level' => 'required|in:beginner,intermediate,advanced,all',
            'language' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'category_id' => 'required|exists:categories,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,webp|max:5120', // 5MB
            'intro_video' => 'nullable|mimes:mp4,mov,avi|max:102400', // 100MB
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Course title is required',
            'description.required' => 'Course description is required',
            'price.min' => 'Price cannot be negative',
            'discount_price.lt' => 'Discount price must be less than regular price',
            'thumbnail.max' => 'Thumbnail must not exceed 5MB',
            'intro_video.max' => 'Intro video must not exceed 100MB',
        ];
    }
}
