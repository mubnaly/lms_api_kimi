<?php

use Illuminate\Support\Facades\Route;
use App\Models\{Course,};
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

/*
|--------------------------------------------------------------------------
| API Routes - Multi-Tenant E-Learning Platform
|--------------------------------------------------------------------------
| Version: 1.0.0
| Rate Limiting: Configured per route group
| Authentication: Laravel Sanctum
*/

// Health Check (No rate limiting)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'service' => config('app.name'),
        'version' => '1.0.0',
        'environment' => app()->environment(),
        'tenant' => tenant()?->name ?? 'central',
    ]);
})->name('health');

// API Documentation
Route::get('/docs', function () {
    return response()->json([
        'message' => 'API Documentation',
        'version' => '1.0.0',
        'base_url' => config('app.url'),
        'endpoints' => [
            'auth' => '/api/v1/login, /api/v1/register',
            'courses' => '/api/v1/courses',
            'documentation' => config('app.url') . '/api/documentation',
        ],
    ]);
})->name('api.docs');

/*
|--------------------------------------------------------------------------
| Public Routes (Rate Limit: 60 requests per minute)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware(['throttle:60,1'])->group(function () {

    // ═══════════════ Authentication ═══════════════
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    // Backward compatibility (without /auth prefix)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // ═══════════════ Categories ═══════════════
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('/flat', [CategoryController::class, 'flat'])->name('flat');
        Route::get('/{category:slug}', [CategoryController::class, 'show'])->name('show');
        Route::get('/{category:slug}/courses', [CategoryController::class, 'courses'])->name('courses');
    });

    // ═══════════════ Courses (Public) ═══════════════
    Route::prefix('courses')->name('courses.')->group(function () {
        Route::get('/', [CourseController::class, 'index'])->name('index');
        Route::get('/{course:slug}', [CourseController::class, 'show'])->name('show');
        Route::get('/{course:slug}/content', [CourseController::class, 'content'])->name('content');
        Route::get('/{course:slug}/reviews', [ReviewController::class, 'index'])->name('reviews');

        // Course instructor info
        Route::get('/{course:slug}/instructor', function (Course $course) {
            return response()->json([
                'success' => true,
                'data' => new \App\Http\Resources\UserResource($course->instructor),
            ]);
        })->name('instructor');

        // Coupon validation (public)
        Route::post('/{course:slug}/coupons/apply', [CouponController::class, 'apply'])->name('coupons.apply');
    });

    // ═══════════════ Instructors ═══════════════
    Route::prefix('instructors')->name('instructors.')->group(function () {
        Route::get('/', [InstructorController::class, 'index'])->name('index');
        Route::get('/{instructor}', [InstructorController::class, 'show'])->name('show');
        Route::get('/{instructor}/courses', [InstructorController::class, 'courses'])->name('courses');
    });

    // ═══════════════ Payment Callbacks ═══════════════
    Route::prefix('payments')->name('payment.')->group(function () {
        Route::post('/{gateway}/callback', [PaymentCallbackController::class, 'handleCallback'])->name('callback');
        Route::get('/{gateway}/return', [PaymentCallbackController::class, 'returnUrl'])->name('return');
    });
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Rate Limit: 120 requests per minute)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {

    // ═══════════════ User Profile ═══════════════
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [AuthController::class, 'profile'])->name('show');
        Route::post('/', [AuthController::class, 'updateProfile'])->name('update');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // ═══════════════ Enrollments ═══════════════
    Route::prefix('enrollments')->name('enrollments.')->group(function () {
        Route::get('/my', [EnrollmentController::class, 'myEnrollments'])->name('my');
        Route::get('/{enrollment}', [EnrollmentController::class, 'show'])->name('show');
    });

    // Backward compatibility
    Route::get('/my-enrollments', [EnrollmentController::class, 'myEnrollments']);

    // ═══════════════ Course Enrollment ═══════════════
    Route::post('/courses/{course:slug}/enroll', [EnrollmentController::class, 'enroll'])->name('courses.enroll');
    Route::post('/verify-payment', [EnrollmentController::class, 'verifyPayment'])->name('payment.verify');

    // ═══════════════ Lesson Access ═══════════════
    Route::prefix('courses/{course:slug}/lessons')->name('lessons.')->group(function () {
        Route::get('/{lesson:slug}', [LessonController::class, 'show'])->name('show');
        Route::get('/{lesson:slug}/content', [LessonController::class, 'content'])->name('content');
        Route::put('/{lesson:slug}/progress', [LessonProgressController::class, 'update'])->name('progress.update');
        Route::post('/{lesson:slug}/complete', [LessonProgressController::class, 'complete'])->name('complete');
    });

    // ═══════════════ Reviews ═══════════════
    Route::prefix('reviews')->name('reviews.')->group(function () {
        Route::post('/courses/{course:slug}', [ReviewController::class, 'store'])->name('store');
        Route::put('/{review}', [ReviewController::class, 'update'])->name('update');
        Route::delete('/{review}', [ReviewController::class, 'destroy'])->name('destroy');
    });

    // Backward compatibility
    Route::post('/courses/{course:slug}/reviews', [ReviewController::class, 'store']);

    // ═══════════════ Wishlist ═══════════════
    Route::prefix('wishlist')->name('wishlist.')->group(function () {
        Route::get('/', [WishlistController::class, 'index'])->name('index');
        Route::post('/courses/{course:slug}', [WishlistController::class, 'toggle'])->name('toggle');
    });

    // Backward compatibility
    Route::post('/courses/{course:slug}/wishlist', [WishlistController::class, 'toggle']);

    // ═══════════════ Become Instructor ═══════════════
    Route::post('/become-instructor', [InstructorController::class, 'becomeInstructor'])->name('instructor.apply');
});

/*
|--------------------------------------------------------------------------
| Instructor Routes (Rate Limit: 60 requests per minute)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware(['auth:sanctum', 'role:instructor|admin', 'throttle:60,1'])->group(function () {

    // ═══════════════ Course Management ═══════════════
    Route::prefix('courses')->name('instructor.courses.')->group(function () {
        Route::post('/', [CourseController::class, 'store'])->name('store');
        Route::put('/{course:slug}', [CourseController::class, 'update'])->name('update');
        Route::delete('/{course:slug}', [CourseController::class, 'destroy'])->name('destroy');
        Route::get('/{course:slug}/analytics', [CourseController::class, 'analytics'])->name('analytics');

        // ═══════════════ Section Management ═══════════════
        Route::post('/{course:slug}/sections', [CourseSectionController::class, 'store'])->name('sections.store');
    });

    Route::prefix('sections')->name('instructor.sections.')->group(function () {
        Route::put('/{section}', [CourseSectionController::class, 'update'])->name('update');
        Route::delete('/{section}', [CourseSectionController::class, 'destroy'])->name('destroy');
        Route::post('/{section}/reorder', [CourseSectionController::class, 'reorder'])->name('reorder');
        Route::post('/{section}/duplicate', [CourseSectionController::class, 'duplicate'])->name('duplicate');
        Route::post('/{section}/toggle-visibility', [CourseSectionController::class, 'toggleVisibility'])->name('toggle-visibility');

        // ═══════════════ Lesson Management ═══════════════
        Route::post('/{section}/lessons', [LessonController::class, 'store'])->name('lessons.store');
    });

    Route::prefix('lessons')->name('instructor.lessons.')->group(function () {
        Route::put('/{lesson}', [LessonController::class, 'update'])->name('update');
        Route::delete('/{lesson}', [LessonController::class, 'destroy'])->name('destroy');
        Route::post('/{lesson}/reorder', [LessonController::class, 'reorder'])->name('reorder');
        Route::post('/{lesson}/duplicate', [LessonController::class, 'duplicate'])->name('duplicate');
        Route::post('/{lesson}/toggle-visibility', [LessonController::class, 'toggleVisibility'])->name('toggle-visibility');
    });

    // ═══════════════ Coupon Management ═══════════════
    Route::prefix('coupons')->name('instructor.coupons.')->group(function () {
        Route::get('/courses/{course:slug}', [CouponController::class, 'index'])->name('index');
        Route::post('/courses/{course:slug}', [CouponController::class, 'store'])->name('store');
        Route::put('/{coupon}', [CouponController::class, 'update'])->name('update');
        Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('destroy');
    });

    // Backward compatibility
    Route::get('/courses/{course:slug}/coupons', [CouponController::class, 'index']);
    Route::post('/courses/{course:slug}/coupons', [CouponController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes (Rate Limit: 120 requests per minute)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'role:admin', 'throttle:120,1'])->name('admin.')->group(function () {

    // ═══════════════ Instructor Management ═══════════════
    Route::prefix('instructors')->name('instructors.')->group(function () {
        Route::get('/pending', [AdminController::class, 'pendingInstructors'])->name('pending');
        Route::post('/{instructor}/approve', [AdminController::class, 'approveInstructor'])->name('approve');
    });

    // Backward compatibility
    Route::get('/pending-instructors', [AdminController::class, 'pendingInstructors']);

    // ═══════════════ Course Management ═══════════════
    Route::prefix('courses')->name('courses.')->group(function () {
        Route::get('/pending', [AdminController::class, 'pendingCourses'])->name('pending');
        Route::post('/{course:slug}/approve', [AdminController::class, 'approveCourse'])->name('approve');
    });

    // Backward compatibility
    Route::get('/pending-courses', [AdminController::class, 'pendingCourses']);

    // ═══════════════ Analytics ═══════════════
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/overview', [AdminController::class, 'platformOverview'])->name('overview');
    });

    // ═══════════════ User Management ═══════════════
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', function (Request $request) {
            $users = \App\Models\User::query()
                ->when($request->role, fn($q, $role) => $q->where('role', $role))
                ->when($request->search, fn($q, $search) =>
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                )
                ->paginate($request->get('per_page', 15));

            return \App\Http\Resources\UserResource::collection($users);
        })->name('index');
    });
});

/*
|--------------------------------------------------------------------------
| Fallback Route
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found',
        'available_versions' => ['v1'],
        'documentation' => config('app.url') . '/api/docs',
    ], 404);
});
