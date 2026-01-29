<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\SumUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SumUpPaymentController extends Controller
{
    private SumUpService $sumUpService;

    private OrderService $orderService;

    public function __construct(SumUpService $sumUpService, OrderService $orderService)
    {
        $this->sumUpService = $sumUpService;
        $this->orderService = $orderService;
    }

    /**
     * Get SumUp public configuration for frontend
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'config' => [
                'public_key' => config('sumup.public_key'),
                'merchant_code' => config('sumup.merchant_code'),
                'environment' => config('sumup.environment'),
                'currency' => config('sumup.checkout.currency'),
                'locale' => config('sumup.checkout.locale'),
            ],
        ]);
    }

    /**
     * Create a checkout session for an order
     */
    public function createCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'uuid', 'exists:orders,id'],
        ]);

        try {
            $order = Order::findOrFail($validated['order_id']);

            if (! $order->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette commande ne peut plus etre payee.',
                ], 400);
            }

            // If checkout already exists, return it
            if ($order->sumup_checkout_id) {
                try {
                    $checkout = $this->sumUpService->getCheckout($order->sumup_checkout_id);
                    if ($checkout['status'] === 'PENDING') {
                        return response()->json([
                            'success' => true,
                            'checkout_id' => $order->sumup_checkout_id,
                            'order_id' => $order->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Checkout expired or invalid, create new one
                }
            }

            $checkout = $this->sumUpService->createCheckout($order);

            return response()->json([
                'success' => true,
                'checkout_id' => $checkout['id'],
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('SumUp checkout creation failed', [
                'order_id' => $validated['order_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la creation du paiement: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle return from SumUp payment page
     */
    public function callback(Request $request): JsonResponse
    {
        $checkoutId = $request->input('checkout_id');
        $orderId = $request->input('order');

        if (! $checkoutId && ! $orderId) {
            return response()->json([
                'success' => false,
                'message' => 'Parametres manquants.',
            ], 400);
        }

        try {
            // Find order
            $order = $orderId
                ? Order::find($orderId)
                : Order::where('sumup_checkout_id', $checkoutId)->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande non trouvee.',
                ], 404);
            }

            // Verify checkout status with SumUp
            $checkoutId = $checkoutId ?? $order->sumup_checkout_id;
            $order = $this->orderService->verifyAndUpdateOrder($checkoutId);

            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => (float) $order->total,
                    'currency' => $order->currency,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('SumUp callback error', [
                'checkout_id' => $checkoutId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la verification du paiement.',
            ], 500);
        }
    }

    /**
     * Verify payment status (polling endpoint for frontend)
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'uuid', 'exists:orders,id'],
        ]);

        try {
            $order = Order::findOrFail($validated['order_id']);

            if ($order->isPaid()) {
                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                    ],
                ]);
            }

            if (! $order->sumup_checkout_id) {
                return response()->json([
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Paiement non initie.',
                ]);
            }

            // Verify with SumUp
            $order = $this->orderService->verifyAndUpdateOrder($order->sumup_checkout_id);

            return response()->json([
                'success' => true,
                'status' => $order->status,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle SumUp webhook notifications
     */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('SumUp webhook received', [
            'payload' => $request->all(),
        ]);

        $payload = $request->all();
        $eventType = $payload['event_type'] ?? null;
        $checkoutId = $payload['id'] ?? $payload['checkout_id'] ?? null;

        if (! $checkoutId) {
            return response()->json(['received' => true]);
        }

        try {
            $order = Order::where('sumup_checkout_id', $checkoutId)->first();

            if (! $order) {
                Log::warning('SumUp webhook: order not found', ['checkout_id' => $checkoutId]);

                return response()->json(['received' => true]);
            }

            // Process based on event type or checkout status
            $status = $payload['status'] ?? null;
            $transactionId = $payload['transaction_id'] ?? null;

            if ($status === 'PAID' || $eventType === 'CHECKOUT_COMPLETED') {
                $this->orderService->completeOrder($order, $transactionId ?? $checkoutId);
            } elseif ($status === 'FAILED' || $eventType === 'CHECKOUT_FAILED') {
                $this->orderService->handleFailedPayment($order);
            }

            return response()->json(['received' => true]);
        } catch (\Exception $e) {
            Log::error('SumUp webhook processing error', [
                'checkout_id' => $checkoutId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['received' => true]);
        }
    }
}
