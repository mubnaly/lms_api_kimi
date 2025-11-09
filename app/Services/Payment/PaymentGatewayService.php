<?php

namespace App\Services\Payment;

use App\Models\{Enrollment, Coupon, Course, User};
use App\Events\{PaymentInitiated, PaymentCompleted, PaymentFailed};
use Illuminate\Support\Facades\{DB, Log, Http, Cache};
use App\Exceptions\PaymentException;

class PaymentGatewayService
{
    protected string $gateway;
    protected array $config;

    public function __construct()
    {
        $this->gateway = config('payment.default_gateway', 'fawry');
        $this->config = config("payment.gateways.{$this->gateway}");

        if (empty($this->config)) {
            throw new \RuntimeException("Payment gateway [{$this->gateway}] not configured");
        }
    }

    /**
     * Process enrollment with payment
     */
    public function processEnrollment(User $user, Course $course, ?string $couponCode = null): array
    {
        return DB::transaction(function () use ($user, $course, $couponCode) {
            // Validate enrollment
            $this->validateEnrollment($user, $course);

            // Calculate final amount
            $pricing = $this->calculatePricing($course, $couponCode);

            // Create enrollment
            $enrollment = $this->createEnrollment($user, $course, $pricing);

            // Handle free enrollment
            if ($pricing['final_amount'] <= 0) {
                return $this->processFreeEnrollment($enrollment);
            }

            // Process paid enrollment
            return $this->processPaidEnrollment($enrollment, $pricing);
        });
    }

    /**
     * Validate enrollment eligibility
     */
    protected function validateEnrollment(User $user, Course $course): void
    {
        // Check if already enrolled
        if ($user->enrollments()->where('course_id', $course->id)->exists()) {
            throw new PaymentException('You are already enrolled in this course');
        }

        // Check if course is published
        if (!$course->is_published || !$course->is_approved) {
            throw new PaymentException('This course is not available for enrollment');
        }

        // Check if user is the instructor
        if ($user->id === $course->instructor_id) {
            throw new PaymentException('You cannot enroll in your own course');
        }
    }

    /**
     * Calculate pricing with coupon
     */
    protected function calculatePricing(Course $course, ?string $couponCode): array
    {
        $originalPrice = $course->final_price;
        $discount = 0;
        $coupon = null;

        if ($couponCode) {
            $coupon = $this->validateCoupon($couponCode, $course, $originalPrice);
            $discount = $coupon->calculateDiscount($originalPrice);
        }

        return [
            'original_price' => $originalPrice,
            'discount' => $discount,
            'final_amount' => max(0, $originalPrice - $discount),
            'coupon' => $coupon,
        ];
    }

    /**
     * Validate and retrieve coupon
     */
    protected function validateCoupon(string $code, Course $course, float $amount): Coupon
    {
        $coupon = Cache::remember(
            "coupon_{$code}",
            300,
            fn() => Coupon::where('code', $code)->valid()->first()
        );

        if (!$coupon) {
            throw new PaymentException('Invalid or expired coupon code');
        }

        if (!$coupon->canBeAppliedTo($course, $amount)) {
            throw new PaymentException('This coupon cannot be applied to this course');
        }

        return $coupon;
    }

    /**
     * Create enrollment record
     */
    protected function createEnrollment(User $user, Course $course, array $pricing): Enrollment
    {
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => $pricing['original_price'],
            'paid_amount' => $pricing['final_amount'],
            'payment_method' => $this->gateway,
            'payment_status' => $pricing['final_amount'] > 0 ? 'pending' : 'completed',
            'enrolled_at' => now(),
            'metadata' => [
                'original_price' => $pricing['original_price'],
                'discount_amount' => $pricing['discount'],
                'coupon_code' => $pricing['coupon']?->code,
                'gateway' => $this->gateway,
                'tenant_id' => tenant()?->id,
            ],
        ]);

        // Increment coupon usage
        if ($pricing['coupon']) {
            $pricing['coupon']->incrementUsage();
        }

        return $enrollment;
    }

    /**
     * Process free enrollment
     */
    protected function processFreeEnrollment(Enrollment $enrollment): array
    {
        $enrollment->update([
            'payment_status' => 'completed',
            'enrolled_at' => now(),
        ]);

        event(new PaymentCompleted($enrollment));

        return [
            'success' => true,
            'enrollment' => $enrollment,
            'payment_url' => null,
            'reference' => null,
            'message' => 'Successfully enrolled in free course',
        ];
    }

    /**
     * Process paid enrollment
     */
    protected function processPaidEnrollment(Enrollment $enrollment, array $pricing): array
    {
        try {
            $paymentData = $this->createPaymentSession($enrollment, $pricing['final_amount']);

            $enrollment->update([
                'transaction_id' => $paymentData['reference'],
                'metadata' => array_merge($enrollment->metadata, [
                    'payment_url' => $paymentData['url'],
                    'payment_created_at' => now()->toISOString(),
                ]),
            ]);

            event(new PaymentInitiated($enrollment, $paymentData));

            return [
                'success' => true,
                'enrollment' => $enrollment,
                'payment_url' => $paymentData['url'],
                'reference' => $paymentData['reference'],
                'gateway' => $this->gateway,
                'message' => 'Payment session created successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Payment session creation failed', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
            ]);

            $enrollment->update(['payment_status' => 'failed']);
            event(new PaymentFailed($enrollment, $e->getMessage()));

            throw new PaymentException('Failed to create payment session: ' . $e->getMessage());
        }
    }

    /**
     * Create payment session based on gateway
     */
    protected function createPaymentSession(Enrollment $enrollment, float $amount): array
    {
        return match($this->gateway) {
            'fawry' => $this->createFawryPayment($enrollment, $amount),
            'paymob' => $this->createPaymobPayment($enrollment, $amount),
            'paytabs' => $this->createPaytabsPayment($enrollment, $amount),
            default => throw new PaymentException("Gateway [{$this->gateway}] not implemented"),
        };
    }

    /**
     * Create Fawry payment
     */
    protected function createFawryPayment(Enrollment $enrollment, float $amount): array
    {
        $reference = $this->generateReference($enrollment);
        $signature = $this->generateFawrySignature($reference, $enrollment->user_id, $amount);

        $params = [
            'merchantCode' => $this->config['merchant_id'],
            'merchantRefNum' => $reference,
            'customerProfileId' => $enrollment->user_id,
            'customerMobile' => $enrollment->user->phone ?? '01000000000',
            'customerEmail' => $enrollment->user->email,
            'amount' => number_format($amount, 2, '.', ''),
            'currencyCode' => 'EGP',
            'description' => "Enrollment in: {$enrollment->course->title}",
            'signature' => $signature,
            'returnUrl' => route('payment.return', ['gateway' => 'fawry']),
        ];

        $url = $this->config['base_url'] . '/ECommerceWeb/Fawry/payments/charge?' . http_build_query($params);

        Log::info('Fawry payment created', [
            'enrollment_id' => $enrollment->id,
            'reference' => $reference,
            'amount' => $amount,
        ]);

        return [
            'url' => $url,
            'reference' => $reference,
        ];
    }

    /**
     * Create PayMob payment
     */
    protected function createPaymobPayment(Enrollment $enrollment, float $amount): array
    {
        $token = $this->getPaymobAuthToken();

        // Create order
        $order = Http::withToken($token)
            ->post("{$this->config['base_url']}/api/ecommerce/orders", [
                'auth_token' => $token,
                'delivery_needed' => false,
                'amount_cents' => (int)($amount * 100),
                'currency' => 'EGP',
                'merchant_order_id' => $this->generateReference($enrollment),
                'items' => [[
                    'name' => $enrollment->course->title,
                    'amount_cents' => (int)($amount * 100),
                    'quantity' => 1,
                ]],
            ])
            ->throw()
            ->json();

        // Create payment key
        $paymentKey = Http::withToken($token)
            ->post("{$this->config['base_url']}/api/acceptance/payment_keys", [
                'auth_token' => $token,
                'amount_cents' => (int)($amount * 100),
                'expiration' => 3600,
                'order_id' => $order['id'],
                'billing_data' => $this->getPaymobBillingData($enrollment->user),
                'currency' => 'EGP',
                'integration_id' => $this->config['integration_id'],
            ])
            ->throw()
            ->json()['token'];

        return [
            'url' => "{$this->config['iframe_url']}?payment_token={$paymentKey}",
            'reference' => (string)$order['id'],
        ];
    }

    /**
     * Create PayTabs payment
     */
    protected function createPaytabsPayment(Enrollment $enrollment, float $amount): array
    {
        $reference = $this->generateReference($enrollment);

        $response = Http::withHeaders([
            'Authorization' => $this->config['server_key'],
            'Content-Type' => 'application/json',
        ])
        ->post("{$this->config['base_url']}/payment/request", [
            'profile_id' => $this->config['profile_id'],
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $reference,
            'cart_description' => "Enrollment in: {$enrollment->course->title}",
            'cart_amount' => $amount,
            'cart_currency' => 'EGP',
            'return' => route('payment.return', ['gateway' => 'paytabs', 'enrollment_id' => $enrollment->id]),
            'callback' => route('payment.callback', ['gateway' => 'paytabs']),
            'customer_details' => $this->getPaytabsCustomerDetails($enrollment->user),
            'hide_shipping' => true,
        ])
        ->throw()
        ->json();

        return [
            'url' => $response['redirect_url'],
            'reference' => $response['tran_ref'],
        ];
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(string $reference, string $enrollmentId): bool
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);

        if ($enrollment->payment_status === 'completed') {
            return true;
        }

        $verified = match($this->gateway) {
            'fawry' => $this->verifyFawryPayment($reference),
            'paymob' => $this->verifyPaymobPayment($reference),
            'paytabs' => $this->verifyPaytabsPayment($reference),
            default => false,
        };

        if ($verified) {
            $enrollment->update([
                'payment_status' => 'completed',
                'enrolled_at' => now(),
                'metadata' => array_merge($enrollment->metadata, [
                    'verified_at' => now()->toISOString(),
                    'payment_reference' => $reference,
                ]),
            ]);

            // Update course enrollment count
            $enrollment->course->increment('students_count');

            event(new PaymentCompleted($enrollment));
        }

        return $verified;
    }

    /**
     * Verify Fawry payment
     */
    protected function verifyFawryPayment(string $reference): bool
    {
        $signature = hash('sha256',
            $this->config['merchant_id'] .
            $reference .
            $this->config['secret']
        );

        $response = Http::get("{$this->config['base_url']}/ECommerceWeb/Fawry/payments/status", [
            'merchantCode' => $this->config['merchant_id'],
            'merchantRefNumber' => $reference,
            'signature' => $signature,
        ])->json();

        Log::info('Fawry payment verification', [
            'reference' => $reference,
            'status' => $response['paymentStatus'] ?? 'unknown',
        ]);

        return isset($response['paymentStatus']) && $response['paymentStatus'] === 'PAID';
    }

    /**
     * Verify PayMob payment
     */
    protected function verifyPaymobPayment(string $reference): bool
    {
        $token = $this->getPaymobAuthToken();

        $response = Http::withToken($token)
            ->get("{$this->config['base_url']}/api/ecommerce/orders/{$reference}")
            ->json();

        return isset($response['paid_amount_cents']) && $response['paid_amount_cents'] > 0;
    }

    /**
     * Verify PayTabs payment
     */
    protected function verifyPaytabsPayment(string $reference): bool
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config['server_key'],
        ])
        ->post("{$this->config['base_url']}/payment/query", [
            'tran_ref' => $reference,
        ])
        ->json();

        return isset($response['payment_result']['response_status']) &&
               $response['payment_result']['response_status'] === 'A';
    }

    /**
     * Generate unique payment reference
     */
    protected function generateReference(Enrollment $enrollment): string
    {
        return "ENRL_{$enrollment->id}_" . time() . '_' . uniqid();
    }

    /**
     * Generate Fawry signature
     */
    protected function generateFawrySignature(string $reference, int $userId, float $amount): string
    {
        return hash('sha256',
            $this->config['merchant_id'] .
            $reference .
            $userId .
            number_format($amount, 2, '.', '') .
            $this->config['secret']
        );
    }

    /**
     * Get PayMob auth token
     */
    protected function getPaymobAuthToken(): string
    {
        return Cache::remember('paymob_auth_token', 3500, function () {
            return Http::post("{$this->config['base_url']}/api/auth/tokens", [
                'api_key' => $this->config['api_key'],
            ])->throw()->json()['token'];
        });
    }

    /**
     * Get PayMob billing data
     */
    protected function getPaymobBillingData(User $user): array
    {
        return [
            'first_name' => explode(' ', $user->name)[0] ?? $user->name,
            'last_name' => explode(' ', $user->name)[1] ?? '',
            'email' => $user->email,
            'phone_number' => $user->phone ?? '01000000000',
            'apartment' => 'NA',
            'floor' => 'NA',
            'street' => 'NA',
            'building' => 'NA',
            'postal_code' => '00000',
            'city' => $user->metadata['city'] ?? 'Cairo',
            'country' => 'EG',
        ];
    }

    /**
     * Get PayTabs customer details
     */
    protected function getPaytabsCustomerDetails(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '01000000000',
            'street1' => 'NA',
            'city' => $user->metadata['city'] ?? 'Cairo',
            'state' => 'NA',
            'country' => 'EG',
            'zip' => '00000',
        ];
    }
}
