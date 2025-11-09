<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Enrollment;
class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = Enrollment::where('user_id', $this->user()->id)
            ->where('course_id', $this->route('course')->id)
            ->where('payment_status', 'completed')
            ->first();

        return $enrollment && !$enrollment->reviews()->exists();
    }

    public function rules(): array
    {
        return [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'required|string|min:10|max:1000',
        ];
    }
}
