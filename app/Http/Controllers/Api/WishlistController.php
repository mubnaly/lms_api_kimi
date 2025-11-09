<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Get user's wishlist courses
     */
    public function index(Request $request)
    {
        $courses = $request->user()
            ->wishlistCourses()
            ->with(['instructor', 'category'])
            ->paginate($request->get('per_page', 15));

        return CourseResource::collection($courses);
    }

    /**
     * Toggle course in wishlist
     */
    public function toggle(Request $request, Course $course)
    {
        $user = $request->user();

        $exists = $user->wishlistCourses()->where('course_id', $course->id)->exists();

        if ($exists) {
            $user->wishlistCourses()->detach($course->id);
            return response()->json([
                'success' => true,
                'message' => 'Removed from wishlist',
                'in_wishlist' => false,
            ]);
        }

        $user->wishlistCourses()->attach($course->id);
        return response()->json([
            'success' => true,
            'message' => 'Added to wishlist',
            'in_wishlist' => true,
        ]);
    }
}
