<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderIdRequest;
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
    public function createCheckout(OrderIdRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $order = Order::findOrFail($validated['order_id']);

            if (! $order->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette commande ne peut plus etre payée.',
                ], 400);
            }

            // If checkout already exists, try to reuse it
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
                    // PAID checkout found — complete the order
                    if ($checkout['status'] === 'PAID') {
                        $this->orderService->completeOrder($order, $checkout['transaction_id'] ?? $order->sumup_checkout_id);

                        return response()->json([
                            'success' => true,
                            'already_paid' => true,
                            'order_id' => $order->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Checkout expired or invalid, create new one
                }
            }

            // Previous checkout failed/expired — create new one with unique reference
            $checkout = $this->sumUpService->createCheckout($order);

            return response()->json([
                'success' => true,
                'checkout_id' => $checkout['id'],
                'order_id' => $order->id,
            ]);
        } catch (\App\Exceptions\BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('SumUp checkout creation failed', [
                'order_id' => $validated['order_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Une erreur s'est produite, veuillez réessayer plus tard. Si l'erreur persiste, n'hésitez pas à me contacter.",
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
    public function verifyPayment(OrderIdRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
        } catch (\App\Exceptions\BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('SumUp verifyPayment failed', [
                'order_id' => $validated['order_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Une erreur s'est produite, veuillez réessayer plus tard. Si l'erreur persiste, n'hésitez pas à me contacter.",
            ], 500);
        }
    }

    /**
     * Handle SumUp webhook notifications
     *
     * Security: Never trust the webhook payload. Always verify the actual
     * checkout status by calling the SumUp API directly.
     */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('SumUp webhook received', [
            'payload' => $request->all(),
        ]);

        $payload = $request->all();
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

            // Already paid — idempotent
            if ($order->isPaid()) {
                return response()->json(['received' => true]);
            }

            // Verify actual status with SumUp API (never trust the payload)
            $checkout = $this->sumUpService->getCheckout($checkoutId);
            $verifiedStatus = $checkout['status'] ?? null;
            $transactionId = $checkout['transaction_id'] ?? null;

            if ($verifiedStatus === 'PAID') {
                $this->orderService->completeOrder($order, $transactionId ?? $checkoutId);
            } elseif ($verifiedStatus === 'FAILED') {
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

    /**
     * Cancel a checkout for an order : deactivate SumUp side AND mark the order as expired.
     *
     * Cela évite que la commande pending soit réutilisée par `findReusablePendingOrder`
     * quand l'utilisateur quitte la page de paiement ou retourne au formulaire « Modifier
     * mes informations ». Chaque nouvelle tentative de paiement repart donc d'une commande
     * fraîche, alignée sur l'état actuel du panier (et de l'utilisateur connecté).
     */
    public function cancelCheckout(OrderIdRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $order = Order::findOrFail($validated['order_id']);

            if (! $order->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cette commande n'est plus en attente de paiement.",
                ], 400);
            }

            if ($order->sumup_checkout_id) {
                try {
                    $this->sumUpService->deactivateCheckout($order->sumup_checkout_id);
                } catch (\Exception $e) {
                    // SumUp peut renvoyer une erreur si le checkout est déjà désactivé.
                    // On log et on continue — l'objectif principal est de fermer l'ordre côté DB.
                    Log::warning('SumUp deactivateCheckout failed (continuing with order expiration)', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $order->update([
                'sumup_checkout_id' => null,
                'status' => 'expired',
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('SumUp cancel checkout failed', [
                'order_id' => $validated['order_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation du checkout.',
            ], 500);
        }
    }
}
