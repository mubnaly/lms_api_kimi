<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Public endpoint
    }

    public function rules()
    {
        return [
            'code' => 'required|string',
            'course_id' => 'required|exists:courses,id',
        ];
    }
}
