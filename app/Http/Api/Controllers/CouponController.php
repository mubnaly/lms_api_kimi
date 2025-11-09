<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\{Course, Coupon};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Cache, Log, DB};

class CouponController extends Controller
{
    protected int $cacheTtl = 300; // 5 minutes

    /**
     * List coupons for a course (instructor only)
     */
    public function index(Course $course)
    {
        Gate::authorize('manage', $course);

        $coupons = Cache::remember(
            "course_{$course->id}_coupons",
            $this->cacheTtl,
            fn() => $course->coupons()
                ->withCount('uses')
                ->latest()
                ->paginate(15)
        );

        return CouponResource::collection($coupons);
    }

    /**
     * Apply coupon to course (public)
     */
    public function apply(Request $request, Course $course)
    {
        $validated = $request->validate([
            'code' => 'required|string|exists:coupons,code',
        ]);

        $coupon = Coupon::where('code', $validated['code'])
            ->valid()
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon is invalid or expired',
            ], 400);
        }

        if ($coupon->course_id && $coupon->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon is not valid for this course',
            ], 400);
        }

        $discount = $coupon->calculateDiscount($course->final_price);
        $finalPrice = max(0, $course->final_price - $discount);

        return response()->json([
            'success' => true,
            'data' => [
                'coupon' => new CouponResource($coupon),
                'original_price' => $course->final_price,
                'discount_amount' => $discount,
                'final_price' => $finalPrice,
            ],
        ]);
    }

    /**
     * Create new coupon (instructor only)
     */
    public function store(Request $request, Course $course)
    {
        Gate::authorize('manage', $course);

        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date|after_or_equal:now',
            'expires_at' => 'nullable|date|after:starts_at',
        ]);

        $coupon = $course->coupons()->create([
            ...$validated,
            'instructor_id' => $request->user()->id,
            'uses_count' => 0,
            'is_active' => true,
        ]);

        Cache::forget("course_{$course->id}_coupons");

        Log::info('Coupon created', [
            'coupon_id' => $coupon->id,
            'course_id' => $course->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'data' => new CouponResource($coupon),
        ], 201);
    }

    /**
     * Update coupon (instructor only)
     */
    public function update(Request $request, Coupon $coupon)
    {
        Gate::authorize('update', $coupon);

        $validated = $request->validate([
            'type' => 'sometimes|required|in:percentage,fixed',
            'value' => 'sometimes|required|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
        ]);

        $coupon->update($validated);

        Cache::forget("course_{$coupon->course_id}_coupons");

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'data' => new CouponResource($coupon->fresh()),
        ]);
    }

    /**
     * Delete coupon (instructor only)
     */
    public function destroy(Coupon $coupon)
    {
        Gate::authorize('delete', $coupon);

        $courseId = $coupon->course_id;
        $coupon->delete();

        Cache::forget("course_{$courseId}_coupons");

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully',
        ]);
    }
}
