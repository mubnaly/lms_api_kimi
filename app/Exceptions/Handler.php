<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\{
    NotFoundHttpException,
    MethodNotAllowedHttpException,
    TooManyRequestsHttpException
};
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $this->logException($e);
        });

        $this->renderable(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $this->handleApiException($e, $request);
            }
        });
    }

    /**
     * Handle API exceptions with consistent format
     */
    protected function handleApiException(Throwable $exception, $request)
    {
        $statusCode = $this->getStatusCode($exception);
        $errorCode = $this->getErrorCode($exception);
        $message = $this->getErrorMessage($exception);

        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $errorCode,
                'status' => $statusCode,
            ],
        ];

        // Add validation errors
        if ($exception instanceof ValidationException) {
            $response['error']['errors'] = $exception->errors();
        }

        // Add debug information in non-production
        if (config('app.debug') && !app()->environment('production')) {
            $response['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->take(5)->toArray(),
            ];
        }

        // Add request ID for tracking
        $response['request_id'] = $request->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid();

        // Add tenant info if available
        if (tenant()) {
            $response['tenant_id'] = tenant()->id;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Get appropriate status code for exception
     */
    protected function getStatusCode(Throwable $exception): int
    {
        return match(true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => 404,
            $exception instanceof MethodNotAllowedHttpException => 405,
            $exception instanceof TooManyRequestsHttpException => 429,
            $exception instanceof PaymentException => 400,
            method_exists($exception, 'getStatusCode') => $exception->getStatusCode(),
            default => 500,
        };
    }

    /**
     * Get error code for exception
     */
    protected function getErrorCode(Throwable $exception): string
    {
        return match(true) {
            $exception instanceof ValidationException => 'VALIDATION_ERROR',
            $exception instanceof AuthenticationException => 'UNAUTHENTICATED',
            $exception instanceof AuthorizationException => 'UNAUTHORIZED',
            $exception instanceof ModelNotFoundException => 'RESOURCE_NOT_FOUND',
            $exception instanceof NotFoundHttpException => 'ENDPOINT_NOT_FOUND',
            $exception instanceof MethodNotAllowedHttpException => 'METHOD_NOT_ALLOWED',
            $exception instanceof TooManyRequestsHttpException => 'RATE_LIMIT_EXCEEDED',
            $exception instanceof PaymentException => 'PAYMENT_ERROR',
            default => 'INTERNAL_SERVER_ERROR',
        };
    }

    /**
     * Get user-friendly error message
     */
    protected function getErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return 'The given data was invalid.';
        }

        if ($exception instanceof AuthenticationException) {
            return 'Unauthenticated. Please log in.';
        }

        if ($exception instanceof AuthorizationException) {
            return 'You are not authorized to perform this action.';
        }

        if ($exception instanceof ModelNotFoundException) {
            return 'The requested resource was not found.';
        }

        if ($exception instanceof NotFoundHttpException) {
            return 'The requested endpoint was not found.';
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return 'The HTTP method is not allowed for this endpoint.';
        }

        if ($exception instanceof TooManyRequestsHttpException) {
            return 'Too many requests. Please slow down.';
        }

        // Use exception message if it's user-friendly
        if ($exception instanceof PaymentException ||
            strlen($exception->getMessage()) < 100) {
            return $exception->getMessage();
        }

        // Generic message for production
        if (app()->environment('production')) {
            return 'An error occurred while processing your request.';
        }

        return $exception->getMessage();
    }

    /**
     * Log exception with context
     */
    protected function logException(Throwable $exception): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_id' => auth()->id(),
            'tenant_id' => tenant()?->id,
        ];

        // Add request body for POST/PUT/PATCH
        if (in_array(request()->method(), ['POST', 'PUT', 'PATCH'])) {
            $context['request_body'] = request()->except(['password', 'password_confirmation']);
        }

        Log::error('Exception occurred', $context);
    }

    /**
     * Handle authentication exception
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Unauthenticated. Please log in.',
                    'code' => 'UNAUTHENTICATED',
                    'status' => 401,
                ],
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}

/**
 * Custom Payment Exception
 */
class PaymentException extends \Exception
{
    public function __construct(string $message = 'Payment processing failed', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
