<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\SumUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private SumUpService $sumUpService,
        private OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with('items.photo', 'user')
            ->whereHas('items', fn ($i) => $i->where('product_type', '!=', 'print_scolaire'))
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"));
            });
        }

        $orders = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'orders' => $orders->map(fn ($order) => self::formatOrder($order)),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(string $orderId): JsonResponse
    {
        $order = Order::with('items.photo', 'user', 'payment')->findOrFail($orderId);

        return response()->json([
            'success' => true,
            'order' => array_merge(self::formatOrder($order), [
                'user' => $order->user ? [
                    'id' => $order->user->id,
                    'email' => $order->user->email,
                    'name' => trim($order->user->first_name.' '.$order->user->last_name),
                ] : null,
                'payment' => $order->payment ? [
                    'id' => $order->payment->id,
                    'provider' => $order->payment->provider,
                    'status' => $order->payment->status,
                    'provider_payment_id' => $order->payment->provider_payment_id,
                ] : null,
                'sumup_checkout_id' => $order->sumup_checkout_id,
                'sumup_transaction_id' => $order->sumup_transaction_id,
            ]),
        ]);
    }

    public function destroy(string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        if ($order->sumup_checkout_id) {
            try {
                $this->sumUpService->deactivateCheckout($order->sumup_checkout_id);
            } catch (\Exception $e) {
                Log::warning('Failed to deactivate SumUp checkout', [
                    'order_id' => $order->id,
                    'checkout_id' => $order->sumup_checkout_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $order->items()->delete();
        $order->payment?->delete();
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commande supprimée.',
        ]);
    }

    public function markShipped(string $orderId): JsonResponse
    {
        $order = Order::with('items')->findOrFail($orderId);

        if ($order->status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les commandes payées peuvent être marquées comme expédiées.',
            ], 400);
        }

        if (! $order->hasPrintItems()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne contient pas de tirages.',
            ], 400);
        }

        $order->markPrintsAsShipped();

        return response()->json([
            'success' => true,
            'message' => 'Commande marquée comme expédiée.',
            'order' => self::formatOrder($order),
        ]);
    }

    /**
     * Get the customer download link for an order (for support)
     */
    public function getDownloadLink(string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        if (! $order->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun lien disponible (commande non payée).',
            ], 400);
        }

        // Regenerate token to ensure it's fresh (resets expiration to 7 days)
        $order->generateDownloadToken();

        $downloadToken = $order->metadata['download_token'];
        $frontendUrl = config('app.frontend_url', 'https://oceanetorresphotographie.fr');
        $downloadLink = "{$frontendUrl}/commande/{$order->id}?token={$downloadToken}";

        return response()->json([
            'success' => true,
            'download_link' => $downloadLink,
        ]);
    }

    /**
     * Re-trigger payment verification and order completion
     */
    public function retryPayment(string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        if ($order->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande est deja payée.',
            ], 400);
        }

        if (! $order->sumup_checkout_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun checkout SumUp associe a cette commande.',
            ], 400);
        }

        try {
            $updatedOrder = $this->orderService->verifyAndUpdateOrder($order->sumup_checkout_id);

            return response()->json([
                'success' => true,
                'message' => $updatedOrder->isPaid()
                    ? 'Paiement confirme. Facture generee et email envoye.'
                    : 'Paiement non confirme sur SumUp. Statut actuel : '.$updatedOrder->status,
                'order' => self::formatOrder($updatedOrder->load('items.photo')),
            ]);
        } catch (\App\Exceptions\BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('Admin retry payment failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'La vérification du paiement a échoué. Veuillez réessayer plus tard.',
            ], 500);
        }
    }

    public function downloadInvoice(string $orderId): JsonResponse
    {
        $order = Order::with('invoice')->findOrFail($orderId);

        $invoice = $order->invoice;
        if (! $invoice || ! $invoice->file_path) {
            try {
                $invoice = $this->invoiceService->generateForOrder($order);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facture non disponible.',
                ], 404);
            }
        }

        $downloadUrl = $this->invoiceService->getDownloadUrl($invoice);
        if (! $downloadUrl) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de générer le lien de téléchargement.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'download_url' => $downloadUrl,
            'filename' => 'facture_'.$invoice->invoice_number.'.pdf',
        ]);
    }

    /**
     * Format order for API response
     */
    public static function formatOrder(Order $order): array
    {
        return (new OrderResource($order))->resolve();
    }
}
