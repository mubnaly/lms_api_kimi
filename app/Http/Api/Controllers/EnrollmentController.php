<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\{Course, Enrollment};
use App\Services\EgyptianPaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Log};

class EnrollmentController extends Controller
{
    public function __construct(protected EgyptianPaymentGatewayService $paymentService) {}

    /**
     * Enroll in course (handles both free and paid)
     */
    public function enroll(Request $request, Course $course)
    {
        $validated = $request->validate([
            'coupon_code' => 'nullable|string|exists:coupons,code',
        ]);

        Gate::authorize('enroll', $course);

        try {
            $result = $this->paymentService->enroll(
                $request->user(),
                $course,
                $validated['coupon_code'] ?? null
            );

            Log::info('Enrollment initiated', [
                'user_id' => $request->user()->id,
                'course_id' => $course->id,
                'payment_method' => $result['gateway'],
            ]);

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Enrollment successful',
                'data' => [
                    'enrollment' => new EnrollmentResource($result['enrollment']),
                    'payment_url' => $result['payment_url'],
                    'reference' => $result['reference'],
                    'gateway' => $result['gateway'],
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Enrollment failed', [
                'user_id' => $request->user()->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's enrollments with progress
     */
    public function myEnrollments(Request $request)
    {
        $enrollments = $request->user()
            ->enrollments()
            ->with(['course' => fn($q) => $q->published()])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return EnrollmentResource::collection($enrollments);
    }

    /**
     * Get specific enrollment details
     */
    public function show(Enrollment $enrollment)
    {
        Gate::authorize('view', $enrollment);

        return response()->json([
            'success' => true,
            'data' => new EnrollmentResource(
                $enrollment->load(['course.instructor', 'lessonProgress'])
            ),
        ]);
    }

    /**
     * Verify payment after gateway callback
     */
    public function verifyPayment(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string',
            'enrollment_id' => 'required|string|exists:enrollments,id',
        ]);

        $success = $this->paymentService->verifyPayment(
            $validated['reference'],
            $validated['enrollment_id']
        );

        if ($success) {
            $enrollment = Enrollment::find($validated['enrollment_id']);

            Log::info('Payment verified', [
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'data' => new EnrollmentResource($enrollment->fresh()),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed',
        ], 400);
    }
}
