<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckoutRequest;
use App\Http\Requests\GetOrdersByEmailRequest;
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
    public function __construct(
        private OrderService $orderService,
        private CartService $cartService,
        private InvoiceService $invoiceService,
    ) {}

    /**
     * Create an order from the current cart
     */
    public function createFromCart(CreateCheckoutRequest $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        $validated = $request->validated();
        $sessionId = $request->header('X-Cart-Session') ?? $validated['session_id'] ?? null;

        try {
            $cart = $this->cartService->getOrCreateCart($user, $sessionId);

            if ($cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le panier est vide.',
                ], 400);
            }

            $shippingData = [
                'shipping_phone' => $validated['shipping_phone'] ?? null,
                'shipping_address_line1' => $validated['shipping_address_line1'] ?? null,
                'shipping_address_line2' => $validated['shipping_address_line2'] ?? null,
                'shipping_postal_code' => $validated['shipping_postal_code'] ?? null,
                'shipping_city' => $validated['shipping_city'] ?? null,
                'shipping_country' => $validated['shipping_country'] ?? 'FR',
            ];

            $order = $this->orderService->createFromCart(
                $cart,
                $user,
                $validated['guest_email'] ?? null,
                $validated['guest_first_name'] ?? null,
                $validated['guest_last_name'] ?? null,
                $request->ip(),
                $shippingData
            );

            $paymentData = $this->orderService->initiatePayment($order);

            return response()->json([
                'success' => true,
                'message' => 'Commande creee.',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'subtotal' => (float) $order->subtotal,
                    'shipping_fee' => (float) $order->shipping_fee,
                    'total' => (float) $order->total,
                    'currency' => $order->currency,
                    'items_count' => $order->items->count(),
                    'shipping' => $order->shipping_fee > 0 ? [
                        'phone' => $order->shipping_phone,
                        'address_line1' => $order->shipping_address_line1,
                        'address_line2' => $order->shipping_address_line2,
                        'postal_code' => $order->shipping_postal_code,
                        'city' => $order->shipping_city,
                        'country' => $order->shipping_country,
                    ] : null,
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
        $user = Auth::guard('sanctum')->user();
        $token = $request->input('token');

        try {
            $order = Order::with('items.photo')->findOrFail($orderId);

            $hasAccess = false;

            if ($user && $order->user_id === $user->id) {
                $hasAccess = true;
            } elseif ($order->guest_email && $request->input('email') === $order->guest_email) {
                $hasAccess = true;
            } elseif ($token && $order->isDownloadTokenValid($token)) {
                $hasAccess = true;
            } elseif ($order->created_at->diffInMinutes(now()) < 30) {
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
                'order' => AdminOrderController::formatOrder($order),
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
            'orders' => $orders->map(fn ($order) => AdminOrderController::formatOrder($order)),
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

            $photo = $item->photo;
            $storagePath = $photo->resolved_storage_path;

            $storageService = app(MinioStorageService::class);
            $downloadUrl = $storageService->getSignedUrl($storagePath, 3600);

            $item->markAsDownloaded();
            $photo->recordDownload($request->ip(), $request->userAgent());

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

            $digitalItems = $order->items->filter(fn ($item) => $item->photo && ! $item->isPrint());
            if ($digitalItems->count() > 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le téléchargement ZIP est limité à 50 photos. Veuillez télécharger les photos individuellement.',
                ], 400);
            }

            $storageService = app(MinioStorageService::class);
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
                $storagePath = $photo->resolved_storage_path;

                try {
                    $tempFile = $storageService->downloadPhoto($storagePath);
                    if ($tempFile && file_exists($tempFile)) {
                        $tempFiles[] = $tempFile;
                        $galleryName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item->gallery_title ?? 'photos');
                        $photoName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item->photo_title ?? $photo->id);
                        $extension = pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'jpg';
                        $filename = "{$galleryName}_{$photoName}.{$extension}";

                        $zip->addFile($tempFile, $filename);
                        $item->markAsDownloaded();
                        $photo->recordDownload($request->ip(), $request->userAgent());
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $zip->close();

            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }

            return Response::download($zipFile, 'commande_'.$order->order_number.'.zip', [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
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
    public function getByEmail(GetOrdersByEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $orders = $this->orderService->getOrdersForEmail($validated['email']);

        return response()->json([
            'success' => true,
            'orders' => $orders->map(fn ($order) => AdminOrderController::formatOrder($order)),
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
}
