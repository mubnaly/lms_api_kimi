<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_amount', 'max_uses', 'uses_count',
        'is_active', 'starts_at', 'expires_at', 'course_id', 'instructor_id'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function isValid()
    {
        if (!$this->is_active) return false;
        if ($this->max_uses && $this->uses_count >= $this->max_uses) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function calculateDiscount(float $amount)
    {
        return $this->type === 'percentage'
            ? ($amount * $this->value) / 100
            : $this->value;
    }
}
