<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EgyptianPaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    /**
     * Handle payment gateway webhooks
     */
    public function handleCallback(Request $request, string $gateway)
    {
        Log::info("Payment callback received", [
            'gateway' => $gateway,
            'payload' => $request->all(),
        ]);

        $service = new EgyptianPaymentGatewayService();

        $reference = $request->input('merchantRefNumber') ?? $request->input('tran_ref');
        $enrollmentId = $request->input('enrollment_id');

        // Verify signature for Fawry
        if ($gateway === 'fawry') {
            $signature = hash('sha256',
                config('payment.gateways.fawry.merchant_id') .
                $reference .
                config('payment.gateways.fawry.secret')
            );

            if ($signature !== $request->input('signature')) {
                Log::warning('Invalid signature in payment callback');
                return response()->json(['success' => false], 400);
            }
        }

        $success = $service->verifyPayment($reference, $enrollmentId);

        return response()->json(['success' => $success]);
    }

    /**
     * Handle return URL from payment gateway (redirects to Flutter)
     */
    public function returnUrl(Request $request, string $gateway)
    {
        Log::info("Payment return URL", [
            'gateway' => $gateway,
            'query' => $request->query(),
        ]);

        $scheme = config('app.flutter_scheme', 'lmsapp');
        $enrollmentId = $request->input('enrollment_id');
        $success = $request->input('success', 'false');

        // Build deep link for Flutter app
        $appUrl = "$scheme://payment/result?" . http_build_query([
            'success' => $success,
            'enrollment_id' => $enrollmentId,
            'reference' => $request->input('merchantRefNumber') ?? $request->input('tran_ref'),
            'gateway' => $gateway,
        ]);

        return redirect()->away($appUrl);
    }
}
