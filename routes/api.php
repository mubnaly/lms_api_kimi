<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    CourseController,
    InstructorController,
    EnrollmentController,
    LessonProgressController,
    ReviewController,
    WishlistController,
    AdminController,
    PaymentCallbackController,
    CategoryController,
    CouponController,
    CourseSectionController,
    LessonController
};
use app\Models\{Course};

/*
=================================================================
                    LMS API ROUTES (v1)
=================================================================
*/

// ────────────── TENANT INFO ──────────────
Route::middleware('tenancy')->get('/tenant', function (Request $request) {
    return response()->json([
        'success' => true,
        'data' => [
            'id' => tenant()->id,
            'name' => tenant()->name,
            'domain' => tenant()->domain,
            'primary_color' => tenant()->primary_color,
            'subscription_ends_at' => tenant()->subscription_ends_at?->toISOString(),
        ],
    ]);
});

// ────────────── PUBLIC ROUTES ──────────────
Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Courses (public)
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course:slug}', [CourseController::class, 'show']);
    Route::get('/courses/{course:slug}/content', [CourseController::class, 'content']);
    Route::get('/courses/{course:slug}/reviews', [ReviewController::class, 'index']);
    Route::get('/courses/{course:slug}/instructor', function (Course $course) {
        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\UserResource($course->instructor),
        ]);
    });

    // Instructors
    Route::get('/instructors', [InstructorController::class, 'index']);
    Route::get('/instructors/{instructor}', [InstructorController::class, 'show']);
    Route::get('/instructors/{instructor}/courses', [InstructorController::class, 'courses']);

    // Category routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/flat', [CategoryController::class, 'flat']);
    Route::get('/categories/{category:slug}', [CategoryController::class, 'show']);
    Route::get('/categories/{category:slug}/courses', [CategoryController::class, 'courses']);

    // Payment callbacks (webhook endpoints)
    Route::post('/payments/{gateway}/callback', [PaymentCallbackController::class, 'handleCallback']);
    Route::get('/payments/{gateway}/return', [PaymentCallbackController::class, 'returnUrl']);

    // Coupons (public check)
    Route::post('/courses/{course:slug}/coupons/apply', [CouponController::class, 'apply']);
});

// ────────────── AUTHENTICATED ROUTES ──────────────
Route::prefix('v1')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    // User Profile
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Enrollments
    Route::post('/courses/{course:slug}/enroll', [EnrollmentController::class, 'enroll']);
    Route::get('/my-enrollments', [EnrollmentController::class, 'myEnrollments']);
    Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show']);
    Route::post('/verify-payment', [EnrollmentController::class, 'verifyPayment']);

    // Lesson Progress
    Route::put('/courses/{course:slug}/lessons/{lesson}/progress', [LessonProgressController::class, 'update']);
    Route::post('/courses/{course:slug}/lessons/{lesson}/complete', [LessonProgressController::class, 'complete']);

    // Reviews
    Route::post('/courses/{course:slug}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/courses/{course:slug}/wishlist', [WishlistController::class, 'toggle']);

    // Become instructor
    Route::post('/become-instructor', [InstructorController::class, 'becomeInstructor']);
});

// ────────────── INSTRUCTOR ROUTES ──────────────
Route::prefix('v1')->middleware(['auth:sanctum', 'role:instructor,admin', 'check.subscription'])->group(function () {
    // Course Management
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{course:slug}', [CourseController::class, 'update']);
    Route::delete('/courses/{course:slug}', [CourseController::class, 'destroy']);
    Route::get('/courses/{course:slug}/analytics', [CourseController::class, 'analytics']);

    // Course Content Management
    Route::post('/courses/{course:slug}/sections', [CourseSectionController::class, 'store']);
    Route::put('/sections/{section}', [CourseSectionController::class, 'update']);
    Route::delete('/sections/{section}', [CourseSectionController::class, 'destroy']);

    // Lessons Management
    Route::post('/sections/{section}/lessons', [LessonController::class, 'store']);
    Route::put('/lessons/{lesson}', [LessonController::class, 'update']);
    Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy']);

    // Instructor Coupons
    Route::get('/courses/{course:slug}/coupons', [CouponController::class, 'index']);
    Route::post('/courses/{course:slug}/coupons', [CouponController::class, 'store']);
    Route::put('/coupons/{coupon}', [CouponController::class, 'update']);
    Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy']);
});

// ────────────── ADMIN ROUTES ──────────────
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'role:admin', 'check.subscription'])->group(function () {
    // Instructor Management
    Route::get('/pending-instructors', [AdminController::class, 'pendingInstructors']);
    Route::post('/instructors/{user}/approve', [AdminController::class, 'approveInstructor']);
    Route::post('/instructors/{user}/reject', [AdminController::class, 'rejectInstructor']);

    // Course Management
    Route::get('/pending-courses', [AdminController::class, 'pendingCourses']);
    Route::post('/courses/{course:slug}/approve', [AdminController::class, 'approveCourse']);
    Route::post('/courses/{course:slug}/reject', [AdminController::class, 'rejectCourse']);

    // Platform Analytics
    Route::get('/analytics/overview', [AdminController::class, 'platformOverview']);
    Route::get('/analytics/revenue', [AdminController::class, 'revenueReport']);
    Route::get('/analytics/users', [AdminController::class, 'userGrowth']);

    // User Management
    Route::get('/users', function (Request $request) {
        $users = \App\Models\User::paginate($request->get('per_page', 15));
        return \App\Http\Resources\UserResource::collection($users);
    });
});

// ────────────── FALLBACK ──────────────
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found. Check API documentation.',
    ], 404);
});
