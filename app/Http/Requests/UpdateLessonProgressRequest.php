<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonProgressRequest extends FormRequest
{
    public function authorize()
    {
        $enrollment = $this->user()->enrollments()
            ->where('course_id', $this->route('course')->id)
            ->where('payment_status', 'completed')
            ->exists();

        return $enrollment;
    }

    public function rules()
    {
        $lesson = $this->route('lesson');

        return [
            'watched_seconds' => 'required|integer|min:0|max:' . $lesson->duration,
            'is_completed' => 'boolean',
        ];
    }
}
