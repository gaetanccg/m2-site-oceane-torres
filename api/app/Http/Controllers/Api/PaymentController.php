<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaymentController extends Controller
{
    public function createStripeIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reservation_id' => ['nullable', 'exists:reservations,id'],
            'type' => ['required', 'in:reservation,gift_card,photo_purchase'],
        ]);

        $user = $request->user();
        $amount = (int) ($validated['amount'] * 100); // Convert to cents

        try {
            $intent = $user->createSetupIntent();

            $payment = Payment::create([
                'user_id' => $user->id,
                'reservation_id' => $validated['reservation_id'] ?? null,
                'provider' => 'stripe',
                'amount' => $validated['amount'],
                'type' => $validated['type'],
                'status' => 'pending',
            ]);

            return response()->json([
                'client_secret' => $intent->client_secret,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création du paiement.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function confirmStripePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'exists:payments,id'],
            'payment_intent_id' => ['required', 'string'],
        ]);

        $payment = Payment::findOrFail($validated['payment_id']);
        $payment->update([
            'provider_payment_id' => $validated['payment_intent_id'],
            'status' => 'completed',
        ]);

        return response()->json([
            'payment' => $payment->fresh(),
            'message' => 'Paiement confirmé avec succès.',
        ]);
    }

    public function createPayPalOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reservation_id' => ['nullable', 'exists:reservations,id'],
            'type' => ['required', 'in:reservation,gift_card,photo_purchase'],
        ]);

        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            $order = $provider->createOrder([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => number_format($validated['amount'], 2, '.', ''),
                    ],
                ]],
            ]);

            $payment = Payment::create([
                'user_id' => $request->user()->id,
                'reservation_id' => $validated['reservation_id'] ?? null,
                'provider' => 'paypal',
                'provider_payment_id' => $order['id'],
                'amount' => $validated['amount'],
                'type' => $validated['type'],
                'status' => 'pending',
            ]);

            return response()->json([
                'order_id' => $order['id'],
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur PayPal.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function capturePayPalOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
            'payment_id' => ['required', 'exists:payments,id'],
        ]);

        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            $result = $provider->capturePaymentOrder($validated['order_id']);

            $payment = Payment::findOrFail($validated['payment_id']);
            $payment->update([
                'status' => $result['status'] === 'COMPLETED' ? 'completed' : 'failed',
            ]);

            return response()->json([
                'payment' => $payment->fresh(),
                'message' => 'Paiement capturé avec succès.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la capture.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function handleStripeWebhook(Request $request): JsonResponse
    {
        // TODO: Implement Stripe webhook handling
        return response()->json(['received' => true]);
    }

    public function handlePayPalWebhook(Request $request): JsonResponse
    {
        // TODO: Implement PayPal webhook handling
        return response()->json(['received' => true]);
    }
}
