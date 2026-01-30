<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy PaymentController - Stripe and PayPal methods removed
 * All payments are now handled via SumUpPaymentController
 */
class PaymentController extends Controller
{
    /**
     * @deprecated Use SumUpPaymentController instead
     */
    public function handleStripeWebhook(Request $request): JsonResponse
    {
        // Stripe is no longer used - return success for any lingering webhooks
        return response()->json(['received' => true, 'message' => 'Stripe webhook deprecated']);
    }

    /**
     * @deprecated Use SumUpPaymentController instead
     */
    public function handlePayPalWebhook(Request $request): JsonResponse
    {
        // PayPal is no longer used - return success for any lingering webhooks
        return response()->json(['received' => true, 'message' => 'PayPal webhook deprecated']);
    }
}
