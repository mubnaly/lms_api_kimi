<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\{HasMedia, InteractsWithMedia};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Course extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'instructor_id', 'category_id', 'title', 'slug', 'subtitle',
        'description', 'requirements', 'outcomes', 'level', 'language',
        'price', 'discount_price', 'duration', 'is_published', 'is_approved',
        'status', 'students_count', 'rating', 'reviews_count', 'metadata'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'is_published' => 'boolean',
        'is_approved' => 'boolean',
        'rating' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($course) => $course->slug ??= Str::slug($course->title));
    }
/**
 * Update course average rating
 */
    public function updateRating(): void
    {
        $this->update([
            'rating' => $this->reviews()->approved()->avg('rating') ?? 0,
            'reviews_count' => $this->reviews()->approved()->count(),
        ]);
    }
    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sections()
    {
        return $this->hasMany(CourseSection::class)->orderBy('order');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function wishlistUsers()
    {
        return $this->belongsToMany(User::class, 'wishlist');
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function getTotalDurationAttribute()
    {
        return $this->lessons()->sum('duration');
    }

    public function getTotalLessonsAttribute()
    {
        return $this->lessons()->count();
    }

    public function getIsFreeAttribute()
    {
        return $this->price <= 0;
    }


    public function getFinalPriceAttribute()
    {
        return $this->discount_price ?? $this->price;
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                    ->where('is_approved', true)
                    ->where('status', 'approved');
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('thumbnail')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function getThumbnailUrlAttribute()
    {
        return $this->getFirstMediaUrl('thumbnail');
    }
}
