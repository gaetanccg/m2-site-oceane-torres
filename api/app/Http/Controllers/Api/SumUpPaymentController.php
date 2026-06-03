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
    public function __construct(
        private SumUpService $sumUpService,
        private OrderService $orderService,
    ) {}

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
     * Handle the SumUp browser redirect after 3DS (GET on return_url).
     *
     * Flow :
     *  1. SumUp redirige le navigateur sur cette URL avec ?checkout_id=… (et notre
     *     propre ?order=… ajouté lors du createCheckout).
     *  2. On vérifie le statut auprès de SumUp côté serveur (idempotent).
     *  3. On redirige le navigateur vers la page de confirmation du SPA.
     *
     * Comme le statut est mis à jour AVANT la redirection, la cliente arrive sur
     * une page de confirmation qui voit l'order déjà en `paid` (ou `failed`),
     * même si le SDK n'a jamais déclenché `onResponse` côté navigateur.
     */
    public function browserReturn(Request $request): \Illuminate\Http\RedirectResponse
    {
        $checkoutId = $request->input('checkout_id');
        $orderId = $request->input('order');
        $frontendUrl = config('app.frontend_url', 'https://oceanetorresphotographie.fr');

        Log::info('SumUp browser return', [
            'order_id' => $orderId,
            'checkout_id' => $checkoutId,
        ]);

        try {
            $order = null;
            if ($orderId) {
                $order = Order::find($orderId);
            }
            if (! $order && $checkoutId) {
                $order = Order::where('sumup_checkout_id', $checkoutId)->first();
            }

            if (! $order) {
                Log::warning('SumUp browser return: order not found', [
                    'order_id' => $orderId,
                    'checkout_id' => $checkoutId,
                ]);

                return redirect($frontendUrl);
            }

            // Idempotent : si l'order n'est pas déjà finalisé on synchronise avec SumUp.
            if (! $order->isPaid() && $order->sumup_checkout_id) {
                try {
                    $order = $this->orderService->verifyAndUpdateOrder($order->sumup_checkout_id);
                } catch (\Exception $e) {
                    Log::error('SumUp browser return: verify failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                    // On continue malgré tout : la page de confirmation a son
                    // propre polling et finira par afficher le bon état.
                }
            }

            return redirect($frontendUrl.'/commande/'.$order->id);
        } catch (\Throwable $e) {
            Log::error('SumUp browser return error', [
                'order_id' => $orderId,
                'checkout_id' => $checkoutId,
                'error' => $e->getMessage(),
            ]);

            return redirect($frontendUrl);
        }
    }

    /**
     * Handle SumUp webhook notifications
     *
     * Security: Never trust the webhook payload. Always verify the actual
     * checkout status by calling the SumUp API directly.
     *
     * Cf. https://developer.sumup.com/online-payments/webhooks :
     *   - POST envoyé sur le `return_url` du checkout
     *   - Payload : { "event_type": "CHECKOUT_STATUS_CHANGED", "id": "<checkout_id>" }
     *   - On doit répondre 2xx pour éviter les retries (1min, 5min, 20min, 2h).
     */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('SumUp webhook received', [
            'payload' => $request->all(),
        ]);

        $payload = $request->all();
        $checkoutId = $payload['id'] ?? $payload['checkout_id'] ?? null;

        // Payload malformé ou nouveau type d'event sans `id` : pas retry-able, on ack
        // pour éviter le spam (la doc SumUp annonce « New events may be introduced
        // at any time, without prior notice »).
        if (! $checkoutId) {
            Log::warning('SumUp webhook: missing checkout id', ['payload' => $payload]);

            return response()->json(['received' => true]);
        }

        try {
            $order = Order::where('sumup_checkout_id', $checkoutId)->first();

            // Order disparue (cancelCheckout, expireAllPendingOrders, suppression
            // admin…) — pas retry-able, on ack.
            if (! $order) {
                Log::warning('SumUp webhook: order not found', ['checkout_id' => $checkoutId]);

                return response()->json(['received' => true]);
            }

            if ($order->isPaid()) {
                return response()->json(['received' => true]);
            }

            $checkout = $this->sumUpService->getCheckout($checkoutId);
            $verifiedStatus = $checkout['status'] ?? null;
            $transactionId = $checkout['transaction_id'] ?? null;

            if ($verifiedStatus === 'PAID') {
                $this->orderService->completeOrder($order, $transactionId ?? $checkoutId);
            } elseif ($verifiedStatus === 'FAILED') {
                $this->orderService->handleFailedPayment($order);
            }
            // PENDING / EXPIRED / autres : on ne touche pas à l'order ici. Le job
            // de réconciliation périodique s'en charge si besoin.

            return response()->json(['received' => true]);
        } catch (\Throwable $e) {
            // Erreur présumée transient (timeout API SumUp, deadlock DB, etc.).
            // On retourne 503 pour bénéficier des retries SumUp (1 min / 5 min /
            // 20 min / 2 h). Si l'erreur est réellement permanente, les retries
            // s'épuiseront en ~2 h et le log permettra d'investiguer.
            Log::error('SumUp webhook processing error — returning 503 for retry', [
                'checkout_id' => $checkoutId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'received' => false,
                'error' => 'transient',
            ], 503);
        }
    }

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

            // Garde-fou contre la race : entre `findOrFail` et cet `update`, un webhook
            // SumUp peut avoir validé le paiement (`deactivateCheckout` ci-dessus fait un
            // round-trip réseau qui ouvre une fenêtre de quelques centaines de ms). Sans
            // la clause `where('status', 'pending')`, on écraserait un `paid` valide.
            $affected = Order::where('id', $order->id)
                ->where('status', 'pending')
                ->update([
                    'sumup_checkout_id' => null,
                    'status' => 'expired',
                ]);

            if ($affected === 0) {
                $order->refresh();
                if ($order->isPaid()) {
                    return response()->json([
                        'success' => false,
                        'already_paid' => true,
                        'message' => 'Votre paiement vient d\'être validé.',
                        'order_id' => $order->id,
                    ], 409);
                }

                return response()->json([
                    'success' => false,
                    'message' => "Cette commande n'est plus en attente de paiement.",
                ], 400);
            }

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
