<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\InvoiceService;
use App\Services\MinioStorageService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class OrderController extends Controller
{
    private OrderService $orderService;

    private CartService $cartService;

    private InvoiceService $invoiceService;

    public function __construct(OrderService $orderService, CartService $cartService, InvoiceService $invoiceService)
    {
        $this->orderService = $orderService;
        $this->cartService = $cartService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * Create an order from the current cart
     */
    public function createFromCart(Request $request): JsonResponse
    {
        // Try to get authenticated user (works on public routes with Bearer token)
        $user = Auth::guard('sanctum')->user();

        // Email is required only for guests (non-authenticated users)
        // CGV acceptance is always required (RGPD compliance)
        $rules = [
            'guest_name' => ['nullable', 'string', 'max:255'],
            'session_id' => ['nullable', 'string'],
            'cgv_accepted' => ['required', 'accepted'],
        ];

        if (! $user) {
            $rules['guest_email'] = ['required', 'email'];
        } else {
            $rules['guest_email'] = ['nullable', 'email'];
        }

        $validated = $request->validate($rules, [
            'cgv_accepted.required' => 'Vous devez accepter les Conditions Générales de Vente.',
            'cgv_accepted.accepted' => 'Vous devez accepter les Conditions Générales de Vente.',
        ]);
        $sessionId = $request->header('X-Cart-Session') ?? $validated['session_id'] ?? null;

        try {
            $cart = $this->cartService->getOrCreateCart($user, $sessionId);

            if ($cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le panier est vide.',
                ], 400);
            }

            $order = $this->orderService->createFromCart(
                $cart,
                $user,
                $validated['guest_email'] ?? null,
                $validated['guest_name'] ?? null,
                $request->ip()
            );

            // Initiate payment
            $paymentData = $this->orderService->initiatePayment($order);

            return response()->json([
                'success' => true,
                'message' => 'Commande creee.',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => (float) $order->total,
                    'currency' => $order->currency,
                    'items_count' => $order->items->count(),
                ],
                'payment' => $paymentData,
            ]);
        } catch (\Exception $e) {
            Log::error('Checkout creation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la creation de votre commande. Veuillez reessayer.',
            ], 400);
        }
    }

    /**
     * Get order details
     */
    public function show(Request $request, string $orderId): JsonResponse
    {
        // Try to get authenticated user (works on public routes with Bearer token)
        $user = Auth::guard('sanctum')->user();
        $token = $request->input('token');

        try {
            $order = Order::with('items.photo')->findOrFail($orderId);

            // Check access
            $hasAccess = false;

            // 1. Authenticated user owns the order
            if ($user && $order->user_id === $user->id) {
                $hasAccess = true;
            }
            // 2. Guest email matches
            elseif ($order->guest_email && $request->input('email') === $order->guest_email) {
                $hasAccess = true;
            }
            // 3. Valid download token
            elseif ($token && $order->isDownloadTokenValid($token)) {
                $hasAccess = true;
            }
            // 4. Order was just created/paid (within 30 minutes) - allows immediate access after payment
            elseif ($order->created_at->diffInMinutes(now()) < 30) {
                $hasAccess = true;
            }

            if (! $hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acces non autorise.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'order' => $this->formatOrder($order),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvee.',
            ], 404);
        }
    }

    /**
     * Get user's orders (authenticated)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentification requise.',
            ], 401);
        }

        $orders = $this->orderService->getOrdersForUser($user);

        return response()->json([
            'success' => true,
            'orders' => $orders->map(fn ($order) => $this->formatOrder($order)),
        ]);
    }

    /**
     * Download photos from a paid order
     */
    public function downloadPhoto(Request $request, string $orderId, string $itemId): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        $token = $request->input('token');

        try {
            $order = $this->orderService->getOrderForDownload($orderId, $token, $user);

            $item = $order->items()->where('id', $itemId)->first();
            if (! $item || ! $item->photo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo non trouvee.',
                ], 404);
            }

            // Get HD or original file path
            $photo = $item->photo;
            $storagePath = $photo->metadata['storage_path'] ?? $photo->file_path;

            // Generate signed URL for download
            $storageService = new MinioStorageService;
            $downloadUrl = $storageService->getSignedUrl($storagePath, 3600);

            // Mark item as downloaded
            $item->markAsDownloaded();

            return response()->json([
                'success' => true,
                'download_url' => $downloadUrl,
                'filename' => basename($storagePath),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Download all photos from an order as ZIP
     */
    public function downloadAll(Request $request, string $orderId)
    {
        $user = Auth::guard('sanctum')->user();
        $token = $request->input('token');

        $tempFiles = [];

        try {
            $order = $this->orderService->getOrderForDownload($orderId, $token, $user);

            // Guard: limit ZIP to 50 photos
            $digitalItems = $order->items->filter(fn ($item) => $item->photo && ! $item->isPrint());
            if ($digitalItems->count() > 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le téléchargement ZIP est limité à 50 photos. Veuillez télécharger les photos individuellement.',
                ], 400);
            }

            $storageService = new MinioStorageService;
            $zipFile = tempnam(sys_get_temp_dir(), 'order_photos_').'.zip';
            $zip = new \ZipArchive;

            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Impossible de créer l\'archive ZIP.');
            }

            foreach ($order->items as $item) {
                if (! $item->photo) {
                    continue;
                }

                $photo = $item->photo;
                $storagePath = $photo->metadata['storage_path'] ?? $photo->file_path;

                try {
                    $tempFile = $storageService->downloadPhoto($storagePath);
                    if ($tempFile && file_exists($tempFile)) {
                        $tempFiles[] = $tempFile;
                        // Clean filename for ZIP
                        $galleryName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item->gallery_title ?? 'photos');
                        $photoName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item->photo_title ?? $photo->id);
                        $extension = pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'jpg';
                        $filename = "{$galleryName}_{$photoName}.{$extension}";

                        $zip->addFile($tempFile, $filename);
                        $item->markAsDownloaded();
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $zip->close();

            // Clean up temp files after ZIP is created
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }

            return Response::download($zipFile, 'commande_'.$order->order_number.'.zip', [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            // Clean up temp files on error
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get orders by guest email
     */
    public function getByEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $orders = $this->orderService->getOrdersForEmail($validated['email']);

        return response()->json([
            'success' => true,
            'orders' => $orders->map(fn ($order) => $this->formatOrder($order)),
        ]);
    }

    /**
     * Admin: List all orders
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Order::with('items.photo', 'user')
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by order number or email
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
            'orders' => $orders->map(fn ($order) => $this->formatOrder($order)),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Admin: Get order details
     */
    public function adminShow(string $orderId): JsonResponse
    {
        $order = Order::with('items.photo', 'user', 'payment')->findOrFail($orderId);

        return response()->json([
            'success' => true,
            'order' => array_merge($this->formatOrder($order), [
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

    /**
     * Admin: Delete any order
     */
    public function adminDestroy(string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        // Deactivate SumUp checkout if exists
        if ($order->sumup_checkout_id) {
            try {
                $sumupService = new \App\Services\SumUpService;
                $sumupService->deactivateCheckout($order->sumup_checkout_id);
            } catch (\Exception $e) {
                // Log but don't block deletion
                \Illuminate\Support\Facades\Log::warning('Failed to deactivate SumUp checkout', [
                    'order_id' => $order->id,
                    'checkout_id' => $order->sumup_checkout_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Delete related items and payment
        $order->items()->delete();
        $order->payment?->delete();
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commande supprimée.',
        ]);
    }

    /**
     * Admin: Mark order prints as shipped
     */
    public function adminMarkShipped(string $orderId): JsonResponse
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
            'order' => $this->formatOrder($order),
        ]);
    }

    /**
     * Download invoice for a paid order (public with token)
     */
    public function downloadInvoice(Request $request, string $orderId): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        $token = $request->input('token');

        try {
            $order = $this->orderService->getOrderForDownload($orderId, $token, $user);

            $invoice = $order->invoice;
            if (! $invoice || ! $invoice->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facture non disponible.',
                ], 404);
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Admin: Download invoice for any order
     */
    public function adminDownloadInvoice(string $orderId): JsonResponse
    {
        $order = Order::with('invoice')->findOrFail($orderId);

        $invoice = $order->invoice;
        if (! $invoice || ! $invoice->file_path) {
            // Try to generate if missing
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
    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'detailed_status' => $order->detailed_status,
            'print_status' => $order->print_status,
            'shipped_at' => $order->shipped_at?->toIso8601String(),
            'subtotal' => (float) $order->subtotal,
            'total' => (float) $order->total,
            'currency' => $order->currency,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'created_at' => $order->created_at->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'photo_id' => $item->photo_id,
                'product_type' => $item->product_type ?? 'digital',
                'product_type_label' => $item->getProductTypeLabel(),
                'is_print' => $item->isPrint(),
                'photo_title' => $item->photo_title,
                'gallery_title' => $item->gallery_title,
                'price' => (float) $item->price,
                'is_downloaded' => $item->is_downloaded,
                'display_url' => $item->photo?->display_url,
                'preview_url' => $item->photo?->preview_url,
                'thumbnail_url' => $item->photo?->thumbnail_url,
            ]),
            'has_prints' => $order->hasPrintItems(),
            'customer_email' => $order->customer_email,
            'customer_name' => $order->customer_name,
        ];
    }
}
