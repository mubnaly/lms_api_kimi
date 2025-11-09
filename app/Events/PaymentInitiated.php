<?php

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

/**
 * Payment Initiated Event
 * Fired when payment session is created
 */
class PaymentInitiated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Enrollment $enrollment;
    public array $paymentData;

    public function __construct(Enrollment $enrollment, array $paymentData)
    {
        $this->enrollment = $enrollment;
        $this->paymentData = $paymentData;
    }
}

/**
 * Payment Completed Event
 * Fired when payment is successfully verified
 */
class PaymentCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Enrollment $enrollment;

    public function __construct(Enrollment $enrollment)
    {
        $this->enrollment = $enrollment;
    }
}

/**
 * Payment Failed Event
 * Fired when payment verification fails
 */
class PaymentFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Enrollment $enrollment;
    public string $reason;

    public function __construct(Enrollment $enrollment, string $reason)
    {
        $this->enrollment = $enrollment;
        $this->reason = $reason;
    }
}

/**
 * Payment Refunded Event
 * Fired when payment is refunded
 */
class PaymentRefunded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Enrollment $enrollment;
    public ?string $reason;

    public function __construct(Enrollment $enrollment, ?string $reason = null)
    {
        $this->enrollment = $enrollment;
        $this->reason = $reason;
    }
}
