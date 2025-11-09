<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray($request)
    {
        $amount = $request->get('amount', 0);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'discount' => $this->calculateDiscount($amount),
            'min_amount' => $this->min_amount,
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'course_id' => $this->course_id,
            'is_valid' => $this->isValid(),
        ];
    }
}
