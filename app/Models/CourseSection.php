<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    protected $fillable = ['course_id', 'title', 'description', 'order', 'is_visible'];

    protected $casts = ['is_visible' => 'boolean'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}/*************  ✨ Windsurf Command ⭐  *************/
/*******  ba879a64-5d3a-4c06-8baa-70f72ec1e936  *******/    /**

     * Scope a query to only include visible sections.

     *

     * @param  \Illuminate\Database\Eloquent\Builder  $query

     * @return \Illuminate\Database\Eloquent\Builder

     */
