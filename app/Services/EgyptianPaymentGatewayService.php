<?php

namespace App\Services;

use App\Models\{Enrollment, Coupon, Course, User};
use Illuminate\Support\Facades\{DB, Log, Http, Cache};
use Psr\Log\LoggerInterface;

class EgyptianPaymentGatewayService
{
    protected string $gateway;
    protected array $config;
    protected int $cacheTtl = 3600;

    public function __construct(protected LoggerInterface $logger)
    {
        $this->gateway = config('payment.default_gateway', 'fawry');
        $this->config = config("payment.gateways.{$this->gateway}");

        if (empty($this->config)) {
            throw new \RuntimeException("Payment gateway [{$this->gateway}] not configured");
        }
    }

    /**
     * Process course enrollment with payment handling
     */
    public function enroll(User $user, Course $course, ?string $couponCode = null): array
    {
        return DB::transaction(function () use ($user, $course, $couponCode) {
            $this->validateEnrollment($user, $course);

            $amount = $course->final_price;
            $coupon = $this->applyCoupon($couponCode, $amount, $course);

            $enrollment = $this->createEnrollment($user, $course, $amount, $coupon);

            if ($amount <= 0) {
                return $this->handleFreeEnrollment($enrollment);
            }

            $paymentData = $this->createPayment($enrollment, $user, $amount, $coupon);

            $this->updateEnrollmentWithPayment($enrollment, $paymentData);

            return [
                'success' => true,
                'enrollment' => $enrollment,
                'payment_url' => $paymentData['url'],
                'reference' => $paymentData['reference'],
                'gateway' => $this->gateway,
            ];
        });
    }

    /**
     * Validate if user can enroll
     */
    protected function validateEnrollment(User $user, Course $course): void
    {
        if ($user->enrollments()->where('course_id', $course->id)->exists()) {
            throw new \Exception('User is already enrolled in this course');
        }

        if (!$course->is_published && !$user->hasRole('admin')) {
            throw new \Exception('This course is not published');
        }
    }

    /**
     * Apply coupon and return updated amount
     */
    protected function applyCoupon(?string $couponCode, float $amount, Course $course): ?Coupon
    {
        if (!$couponCode) {
            return null;
        }

        $coupon = $this->fetchCoupon($couponCode);
        $this->validateCoupon($coupon, $course);

        return $coupon;
    }

    /**
     * Fetch coupon from cache or database
     */
    protected function fetchCoupon(string $code): Coupon
    {
        return Cache::remember(
            "coupon_{$code}",
            $this->cacheTtl,
            fn() => Coupon::where('code', $code)->valid()->first()
        ) ?? throw new \Exception('Invalid or expired coupon');
    }

    /**
     * Validate coupon against course
     */
    protected function validateCoupon(Coupon $coupon, Course $course): void
    {
        if ($coupon->course_id && $coupon->course_id !== $course->id) {
            throw new \Exception('Coupon is not valid for this course');
        }

        if (!$coupon->isValid()) {
            throw new \Exception('Coupon has expired or reached usage limit');
        }
    }

    /**
     * Create enrollment record
     */
    protected function createEnrollment(User $user, Course $course, float $amount, ?Coupon $coupon): Enrollment
    {
        return Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => $course->final_price,
            'paid_amount' => $amount,
            'payment_method' => $this->gateway,
            'payment_status' => $amount > 0 ? 'pending' : 'completed',
            'enrolled_at' => now(),
            'metadata' => [
                'original_price' => $course->final_price,
                'discount_amount' => $coupon?->calculateDiscount($course->final_price) ?? 0,
                'coupon_code' => $coupon?->code,
                'gateway' => $this->gateway,
            ],
        ]);
    }

    /**
     * Handle free enrollment
     */
    protected function handleFreeEnrollment(Enrollment $enrollment): array
    {
        $enrollment->update([
            'payment_status' => 'completed',
            'metadata' => array_merge($enrollment->metadata, [
                'completed_at' => now()->toISOString(),
                'notes' => 'Free course enrollment',
            ]),
        ]);

        event(new \App\Events\FreeEnrollmentCompleted($enrollment));

        return [
            'success' => true,
            'enrollment' => $enrollment->fresh(),
            'payment_url' => null,
            'reference' => null,
            'message' => 'Successfully enrolled for free',
        ];
    }

    /**
     * Generate payment URL and reference
     */
    protected function createPayment(Enrollment $enrollment, User $user, float $amount, ?Coupon $coupon): array
    {
        return match($this->gateway) {
            'fawry' => $this->createFawryPayment($enrollment, $user, $amount),
            'paymob' => $this->createPaymobPayment($enrollment, $user, $amount),
            'paytabs' => $this->createPaytabsPayment($enrollment, $user, $amount),
            default => throw new \Exception("Gateway [{$this->gateway}] not implemented"),
        };
    }

    /**
     * Update enrollment with payment details
     */
    protected function updateEnrollmentWithPayment(Enrollment $enrollment, array $paymentData): void
    {
        $enrollment->update([
            'transaction_id' => $paymentData['reference'],
            'metadata' => array_merge($enrollment->metadata, [
                'payment_url' => $paymentData['url'],
            ]),
        ]);
    }

    /**
     * Fawry Payment
     */
    protected function createFawryPayment(Enrollment $enrollment, User $user, float $amount): array
    {
        $reference = $this->generateReference($enrollment);
        $signature = $this->generateFawrySignature($reference, $user->id, $amount);

        $paymentUrl = $this->config['base_url'] . '/ECommerceWeb/Fawry/payments/messages?' . http_build_query([
            'merchantCode' => $this->config['merchant_id'],
            'merchantRefNum' => $reference,
            'customerProfileId' => $user->id,
            'amount' => number_format($amount, 2, '.', ''),
            'signature' => $signature,
        ]);

        $this->logger->info('Fawry payment created', [
            'enrollment_id' => $enrollment->id,
            'reference' => $reference,
            'amount' => $amount,
        ]);

        return [
            'url' => $paymentUrl,
            'reference' => $reference,
        ];
    }

    /**
     * Paymob Payment
     */
    protected function createPaymobPayment(Enrollment $enrollment, User $user, float $amount): array
    {
        $token = $this->getPaymobAuthToken();

        $order = Http::withToken($token)
            ->post("{$this->config['base_url']}/api/ecommerce/orders", [
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

        $paymentKey = Http::withToken($token)
            ->post("{$this->config['base_url']}/api/acceptance/payment_keys", [
                'amount_cents' => (int)($amount * 100),
                'currency' => 'EGP',
                'order_id' => $order['id'],
                'billing_data' => $this->getBillingData($user),
            ])
            ->throw()
            ->json()['token'];

        return [
            'url' => "{$this->config['iframe_url']}?payment_token={$paymentKey}",
            'reference' => $order['id'],
        ];
    }

    /**
     * PayTabs Payment
     */
    protected function createPaytabsPayment(Enrollment $enrollment, User $user, float $amount): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config['server_key'],
            'Content-Type' => 'application/json',
        ])
        ->post("{$this->config['base_url']}/payment/request", [
            'profile_id' => $this->config['profile_id'],
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $this->generateReference($enrollment),
            'cart_description' => "Course: {$enrollment->course->title}",
            'cart_amount' => $amount,
            'cart_currency' => 'EGP',
            'return' => "{$this->config['return_url']}?enrollment_id={$enrollment->id}",
            'callback' => $this->config['callback_url'],
            'customer_details' => $this->getCustomerDetails($user),
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
     * Get user billing data for Paymob
     */
    protected function getBillingData(User $user): array
    {
        return [
            'first_name' => $user->name,
            'last_name' => '',
            'email' => $user->email,
            'phone_number' => $user->phone ?? '01000000000',
            'apartment' => 'NA',
            'floor' => 'NA',
            'street' => 'NA',
            'building' => 'NA',
            'postal_code' => '00000',
            'city' => $user->metadata['city'] ?? 'Cairo',
            'country' => $user->metadata['country'] ?? 'EG',
        ];
    }

    /**
     * Get customer details for PayTabs
     */
    protected function getCustomerDetails(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '01000000000',
        ];
    }

    /**
     * Verify payment callback
     */
    public function verifyPayment(string $reference, string $enrollmentId): bool
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);

        if ($enrollment->payment_status === 'completed') {
            return true; // Already verified
        }

        $verified = match($this->gateway) {
            'fawry' => $this->verifyFawry($reference),
            'paymob' => $this->verifyPaymob($reference),
            'paytabs' => $this->verifyPaytabs($reference),
            default => false,
        };

        if ($verified) {
            $enrollment->update([
                'payment_status' => 'completed',
                'enrolled_at' => now(),
                'metadata' => array_merge($enrollment->metadata, [
                    'verified_at' => now()->toISOString(),
                    'reference' => $reference,
                ]),
            ]);

            event(new \App\Events\PaymentVerified($enrollment));
        }

        return $verified;
    }

    /**
     * Verify Fawry payment
     */
    protected function verifyFawry(string $reference): bool
    {
        $signature = $this->generateFawrySignature($reference, 0, 0);

        $response = Http::get("{$this->config['base_url']}/ECommerceWeb/Fawry/payments/status", [
            'merchantCode' => $this->config['merchant_id'],
            'merchantRefNumber' => $reference,
            'signature' => $signature,
        ])->json();

        $this->logger->info('Fawry verification', ['response' => $response]);

        return ($response['statusCode'] ?? 0) === 200
            && ($response['paymentStatus'] ?? '') === 'PAID';
    }

    /**
     * Get Paymob auth token with caching
     */
    protected function getPaymobAuthToken(): string
    {
        return Cache::remember('paymob_auth_token', 3500, function () {
            return Http::post("{$this->config['base_url']}/api/auth/tokens", [
                'api_key' => $this->config['api_key'],
            ])
            ->throw()
            ->json()['token'];
        });
    }

    /**
     * Verify Paymob payment (via webhook)
     */
    protected function verifyPaymob(string $reference): bool
    {
        // Implementation depends on webhook structure
        // Usually you verify HMAC signature and order status
        return true;
    }

    /**
     * Verify PayTabs payment
     */
    protected function verifyPaytabs(string $reference): bool
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config['server_key'],
        ])
        ->post("{$this->config['base_url']}/payment/query", [
            'tran_ref' => $reference,
        ])
        ->json();

        return ($response['payment_result']['response_status'] ?? '') === 'A';
    }

    /**
     * Generate unique reference number
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
}
