<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Coupon;
use App\Models\User;
use Stripe\StripeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.secret'));
    }

    /**
     * Process course enrollment payment
     */
    public function enroll(User $user, Course $course, ?string $couponCode = null): array
    {
        return DB::transaction(function () use ($user, $course, $couponCode) {
            // Check if already enrolled
            if ($user->enrollments()->where('course_id', $course->id)->exists()) {
                throw new \Exception('User is already enrolled in this course');
            }

            $amount = $course->final_price;
            $discount = 0;
            $coupon = null;

            // Apply coupon if provided
            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)->valid()->first();
                if (!$coupon || ($coupon->course_id && $coupon->course_id !== $course->id)) {
                    throw new \Exception('Invalid or expired coupon');
                }
                $discount = $coupon->calculateDiscount($amount);
                $amount = max(0, $amount - $discount);
            }

            // Create enrollment record
            $enrollment = Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'price' => $course->final_price,
                'paid_amount' => $amount,
                'payment_method' => 'stripe',
                'payment_status' => $amount > 0 ? 'pending' : 'completed',
                'enrolled_at' => now(),
            ]);

            // For free courses, complete immediately
            if ($amount <= 0) {
                $enrollment->update(['payment_status' => 'completed']);
                return [
                    'success' => true,
                    'enrollment' => $enrollment,
                    'payment_intent' => null,
                ];
            }

            // Create Stripe Payment Intent
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => 'usd',
                'customer' => $user->stripe_id ?? $this->createStripeCustomer($user),
                'metadata' => [
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $course->id,
                    'user_id' => $user->id,
                    'coupon_id' => $coupon?->id,
                ],
            ]);

            // Update enrollment with payment intent
            $enrollment->update([
                'transaction_id' => $paymentIntent->id,
            ]);

            // Increment coupon usage
            if ($coupon) {
                $coupon->increment('uses_count');
            }

            return [
                'success' => true,
                'enrollment' => $enrollment,
                'payment_intent' => $paymentIntent,
                'client_secret' => $paymentIntent->client_secret,
            ];
        });
    }

    /**
     * Confirm payment after checkout
     */
    public function confirmPayment(string $paymentIntentId): bool
    {
        $enrollment = Enrollment::where('transaction_id', $paymentIntentId)->firstOrFail();

        $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

        if ($paymentIntent->status === 'succeeded') {
            $enrollment->update([
                'payment_status' => 'completed',
                'enrolled_at' => now(),
                'metadata' => array_merge($enrollment->metadata ?? [], [
                    'payment_confirmed_at' => now(),
                    'stripe_payment_method' => $paymentIntent->payment_method,
                ]),
            ]);

            // Send confirmation email
            \App\Events\EnrollmentCompleted::dispatch($enrollment);

            return true;
        }

        return false;
    }

    /**
     * Create Stripe customer
     */
    private function createStripeCustomer(User $user): string
    {
        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        $user->update(['stripe_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Handle refund
     */
    public function refund(Enrollment $enrollment, ?string $reason = null): bool
    {
        try {
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $enrollment->transaction_id,
                'reason' => $reason ?? 'requested_by_customer',
            ]);

            $enrollment->update([
                'payment_status' => 'refunded',
                'metadata' => array_merge($enrollment->metadata ?? [], [
                    'refunded_at' => now(),
                    'refund_reason' => $reason,
                ]),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Refund failed', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
