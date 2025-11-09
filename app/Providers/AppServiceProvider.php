<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Response, Schema, DB, Log};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register payment service
        $this->app->singleton(
            \App\Services\Payment\PaymentGatewayService::class,
            function ($app) {
                return new \App\Services\Payment\PaymentGatewayService();
            }
        );

        // Register course service
        $this->app->singleton(
            \App\Services\CourseService::class,
            function ($app) {
                return new \App\Services\CourseService();
            }
        );

        // Register video service
        $this->app->singleton(
            \App\Services\VideoService::class,
            function ($app) {
                return new \App\Services\VideoService();
            }
        );

        // Register custom helpers
        $this->registerHelpers();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for older MySQL versions
        Schema::defaultStringLength(191);

        // Disable resource wrapping globally
        JsonResource::withoutWrapping();

        // Register custom response macros
        $this->registerResponseMacros();

        // Configure model behavior
        $this->configureModels();

        // Register observers
        $this->registerObservers();

        // Configure query logging in development
        $this->configureQueryLogging();

        // Register custom validation rules
        $this->registerValidationRules();
    }

    /**
     * Register custom response macros
     */
    protected function registerResponseMacros(): void
    {
        // Success response macro
        Response::macro('success', function ($data = null, string $message = 'Success', int $status = 200) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
            ], $status);
        });

        // Error response macro
        Response::macro('error', function (string $message, int $status = 400, ?string $code = null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $message,
                    'code' => $code ?? 'ERROR',
                    'status' => $status,
                ],
            ], $status);
        });

        // Paginated response macro
        Response::macro('paginated', function ($resource, string $message = 'Success') {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $resource->items(),
                'meta' => [
                    'current_page' => $resource->currentPage(),
                    'last_page' => $resource->lastPage(),
                    'per_page' => $resource->perPage(),
                    'total' => $resource->total(),
                    'from' => $resource->firstItem(),
                    'to' => $resource->lastItem(),
                ],
                'links' => [
                    'first' => $resource->url(1),
                    'last' => $resource->url($resource->lastPage()),
                    'prev' => $resource->previousPageUrl(),
                    'next' => $resource->nextPageUrl(),
                ],
            ]);
        });
    }

    /**
     * Configure model behavior
     */
    protected function configureModels(): void
    {
        // Prevent lazy loading in production
        Model::preventLazyLoading(!app()->isProduction());

        // Prevent accessing missing attributes
        Model::preventAccessingMissingAttributes(!app()->isProduction());

        // Prevent silently discarding attributes
        Model::preventSilentlyDiscardingAttributes(!app()->isProduction());
    }

    /**
     * Register model observers
     */
    protected function registerObservers(): void
    {
        \App\Models\Course::observe(\App\Observers\CourseObserver::class);
        \App\Models\Enrollment::observe(\App\Observers\EnrollmentObserver::class);
        \App\Models\User::observe(\App\Observers\UserObserver::class);
    }

    /**
     * Configure query logging in development
     */
    protected function configureQueryLogging(): void
    {
        if (config('app.debug') && app()->environment('local')) {
            DB::listen(function ($query) {
                Log::channel('single')->info('Query executed', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                ]);
            });
        }
    }

    /**
     * Register custom validation rules
     */
    protected function registerValidationRules(): void
    {
        \Illuminate\Support\Facades\Validator::extend('phone_number', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^01[0125][0-9]{8}$/', $value);
        }, 'The :attribute must be a valid Egyptian phone number.');

        \Illuminate\Support\Facades\Validator::extend('video_url', function ($attribute, $value, $parameters, $validator) {
            $videoService = app(\App\Services\VideoService::class);
            return $videoService->validateVideoUrl($value);
        }, 'The :attribute must be a valid video URL from supported platforms.');
    }

    /**
     * Register helper functions
     */
    protected function registerHelpers(): void
    {
        require_once app_path('Helpers/helpers.php');
    }
}
