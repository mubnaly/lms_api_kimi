<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\{Course, Review, Enrollment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    /**
     * List course reviews (paginated)
     */
    public function index(Course $course)
    {
        $reviews = $course->reviews()
            ->approved()
            ->with('user')
            ->latest()
            ->paginate(10);

        return ReviewResource::collection($reviews);
    }

    /**
     * Submit new review
     */
    public function store(Request $request, Course $course)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'required|string|min:10|max:1000',
        ]);

        $enrollment = $request->user()->enrollments()
            ->where('course_id', $course->id)
            ->where('payment_status', 'completed')
            ->first();

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'You must complete the course to review it',
            ], 403);
        }

        if ($enrollment->reviews()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this course',
            ], 400);
        }

        $review = $enrollment->reviews()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
            'is_approved' => false, // Admin must approve
        ]);

        // Update course rating
        $course->updateRating();

        return response()->json([
            'success' => true,
            'message' => 'Review submitted for approval',
            'data' => new ReviewResource($review->load('user')),
        ], 201);
    }

    /**
     * Update own review
     */
    public function update(Request $request, Review $review)
    {
        Gate::authorize('update', $review);

        $validated = $request->validate([
            'rating' => 'sometimes|required|integer|between:1,5',
            'comment' => 'sometimes|required|string|min:10|max:1000',
        ]);

        $review->update($validated);

        $review->course->updateRating();

        return response()->json([
            'success' => true,
            'data' => new ReviewResource($review->fresh()->load('user')),
        ]);
    }

    /**
     * Delete own review
     */
    public function destroy(Review $review)
    {
        Gate::authorize('delete', $review);

        $course = $review->course;
        $review->delete();

        $course->updateRating();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted',
        ]);
    }
}
