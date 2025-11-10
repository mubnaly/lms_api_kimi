<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\{Course, Enrollment, User, Category};
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
    LessonController,
    CertificateController
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
})->name('api.health');

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
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
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

    // ═══════════════ Public Certificate Verification ═══════════════
    Route::post('/certificates/verify', [CertificateController::class, 'verify'])->name('certificates.verify');

    // ═══════════════ Search & Filter ═══════════════
    Route::prefix('search')->name('search.')->group(function () {
        // Global search
        Route::get('/', function (Request $request) {
            $query = $request->input('q');

            if (!$query || strlen($query) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search query must be at least 2 characters',
                ], 400);
            }

            $courses = Course::published()
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhere('subtitle', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get();

            $instructors = User::instructors()
                ->verified()
                ->where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get();

            $categories = Category::active()
                ->where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'courses' => \App\Http\Resources\CourseResource::collection($courses),
                    'instructors' => \App\Http\Resources\UserResource::collection($instructors),
                    'categories' => \App\Http\Resources\CategoryResource::collection($categories),
                ],
            ]);
        })->name('global');

        // Advanced filter
        Route::post('/filter', function (Request $request) {
            $query = Course::published();

            if ($request->filled('categories')) {
                $query->whereIn('category_id', $request->categories);
            }

            if ($request->filled('levels')) {
                $query->whereIn('level', $request->levels);
            }

            if ($request->filled('price_range')) {
                [$min, $max] = $request->price_range;
                $query->whereBetween('price', [$min, $max]);
            }

            if ($request->filled('rating')) {
                $query->where('rating', '>=', $request->rating);
            }

            if ($request->filled('duration')) {
                $query->where('duration', '<=', $request->duration * 3600);
            }

            $courses = $query->paginate($request->get('per_page', 15));

            return \App\Http\Resources\CourseResource::collection($courses);
        })->name('filter');
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

    // ═══════════════ Certificates ═══════════════
    Route::prefix('certificates')->name('certificates.')->group(function () {
        Route::get('/', [CertificateController::class, 'index'])->name('index');
        Route::get('/{enrollment}', [CertificateController::class, 'show'])->name('show');
        Route::get('/{enrollment}/download', [CertificateController::class, 'download'])->name('download');
    });

    // ═══════════════ Analytics ═══════════════
    Route::prefix('analytics')->name('analytics.')->group(function () {
        // Student analytics
        Route::get('/student/dashboard', function (Request $request) {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_courses' => $user->enrollments()->completed()->count(),
                    'completed_courses' => $user->enrollments()->finished()->count(),
                    'in_progress' => $user->enrollments()->active()->count(),
                    'total_certificates' => $user->enrollments()->finished()->count(),
                    'total_time_spent' => $user->lessonProgress()->sum('watched_seconds'),
                    'recent_activity' => $user->lessonProgress()
                        ->with('lesson.course')
                        ->latest('last_watched_at')
                        ->limit(5)
                        ->get(),
                ],
            ]);
        })->name('student.dashboard');

        // Instructor analytics
        Route::get('/instructor/dashboard', function (Request $request) {
            $instructor = $request->user();

            if (!$instructor->hasRole('instructor')) {
                abort(403);
            }

            $courses = $instructor->courses()->published();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_courses' => $courses->count(),
                    'total_students' => $instructor->total_students,
                    'total_revenue' => $instructor->total_revenue,
                    'average_rating' => $courses->avg('rating'),
                    'total_reviews' => $courses->sum('reviews_count'),
                    'monthly_revenue' => Enrollment::whereIn('course_id', $courses->pluck('id'))
                        ->completed()
                        ->whereMonth('enrolled_at', now()->month)
                        ->sum('paid_amount'),
                    'recent_enrollments' => Enrollment::whereIn('course_id', $courses->pluck('id'))
                        ->with(['user', 'course'])
                        ->latest()
                        ->limit(10)
                        ->get(),
                ],
            ]);
        })->name('instructor.dashboard');
    });

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

    // ═══════════════ Instructor Onboarding ═══════════════
    Route::prefix('instructor')->name('instructor.')->group(function () {
        // Get onboarding progress
        Route::get('/onboarding', function (Request $request) {
            $service = app(\App\Services\InstructorOnboardingService::class);
            return response()->json([
                'success' => true,
                'data' => $service->getOnboardingProgress($request->user()),
            ]);
        })->name('onboarding.progress');

        // Update onboarding step
        Route::post('/onboarding/{step}', function (Request $request, string $step) {
            $service = app(\App\Services\InstructorOnboardingService::class);
            $service->updateOnboardingProgress($request->user(), $step);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding step completed',
            ]);
        })->name('onboarding.update');
    });
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
            $users = User::query()
                ->when($request->role, fn($q, $role) => $q->where('role', $role))
                ->when($request->search, fn($q, $search) =>
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                )
                ->paginate($request->get('per_page', 15));

            return \App\Http\Resources\UserResource::collection($users);
        })->name('index');
    });

    // ═══════════════ Bulk Operations ═══════════════
    Route::prefix('bulk')->name('bulk.')->group(function () {
        // Bulk publish courses
        Route::post('/courses/publish', function (Request $request) {
            $request->validate([
                'course_ids' => 'required|array',
                'course_ids.*' => 'exists:courses,id',
            ]);

            $updated = Course::whereIn('id', $request->course_ids)
                ->update([
                    'is_published' => true,
                    'status' => 'pending',
                ]);

            return response()->json([
                'success' => true,
                'message' => "{$updated} courses published successfully",
            ]);
        })->name('courses.publish');

        // Bulk delete courses
        Route::delete('/courses', function (Request $request) {
            $request->validate([
                'course_ids' => 'required|array',
                'course_ids.*' => 'exists:courses,id',
            ]);

            $query = Course::whereIn('id', $request->course_ids);

            // Check for enrollments
            $coursesWithEnrollments = $query->whereHas('enrollments', function ($q) {
                $q->where('payment_status', 'completed');
            })->count();

            if ($coursesWithEnrollments > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete courses with active enrollments',
                ], 400);
            }

            $deleted = $query->delete();

            return response()->json([
                'success' => true,
                'message' => "{$deleted} courses deleted successfully",
            ]);
        })->name('courses.delete');
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
