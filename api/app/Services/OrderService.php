<?php

namespace App\Services;

use App\Mail\OrderConfirmationMail;
use App\Mail\PrintOrderNotificationMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderService
{
    private SumUpService $sumUpService;

    private InvoiceService $invoiceService;

    public function __construct(SumUpService $sumUpService, InvoiceService $invoiceService)
    {
        $this->sumUpService = $sumUpService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * Create an order from a cart (or return existing pending order)
     */
    public function createFromCart(Cart $cart, ?User $user = null, ?string $guestEmail = null, ?string $guestName = null, ?string $consentIp = null): Order
    {
        // Check if there's already a pending order for this cart
        $existingOrder = Order::where('cart_id', $cart->id)
            ->where('status', 'pending')
            ->with('items')
            ->first();

        if ($existingOrder) {
            Log::info('Returning existing pending order', [
                'order_id' => $existingOrder->id,
                'cart_id' => $cart->id,
            ]);

            return $existingOrder;
        }

        if ($cart->items->isEmpty()) {
            throw new \Exception('Le panier est vide.');
        }

        return DB::transaction(function () use ($cart, $user, $guestEmail, $guestName, $consentIp) {
            $cart->load('items.photo.gallery.galleryProductTypes.packTiers');

            // Re-validate cart items before creating order
            $validItems = [];

            // Count items per gallery+type for pack pricing
            $groupCounts = $cart->items->groupBy(fn ($item) => ($item->photo->gallery_id ?? '').'|'.($item->product_type ?? 'digital')
            )->map->count();

            foreach ($cart->items as $item) {
                // Photo must still exist
                if (! $item->photo) {
                    Log::warning('Cart item photo no longer exists, skipping', ['cart_item_id' => $item->id]);

                    continue;
                }

                // Photo must still be purchasable
                if (! $item->photo->is_purchasable) {
                    Log::warning('Cart item photo no longer purchasable, skipping', [
                        'cart_item_id' => $item->id,
                        'photo_id' => $item->photo_id,
                    ]);

                    continue;
                }

                // Verify product type is still available for this gallery
                $productType = $item->product_type ?? 'digital';
                if (! array_key_exists($productType, CartItem::PRODUCT_TYPES)) {
                    $productType = 'digital';
                }

                // Resolve price using pack pricing
                $gallery = $item->photo->gallery;
                $groupKey = ($gallery?->id ?? '').'|'.$productType;
                $quantity = $groupCounts[$groupKey] ?? 1;
                $currentPrice = $gallery?->resolvePackPrice($productType, $quantity)
                    ?? $gallery?->getPriceForProductType($productType)
                    ?? CartItem::getPriceForType($productType);

                if ((float) $item->price !== $currentPrice) {
                    $item->update(['price' => $currentPrice]);
                    $item->price = $currentPrice;
                }

                $validItems[] = $item;
            }

            if (empty($validItems)) {
                throw new \Exception('Le panier ne contient aucun article valide.');
            }

            $subtotal = collect($validItems)->sum('price');
            $total = $subtotal; // No tax for now

            $order = Order::create([
                'user_id' => $user?->id,
                'cart_id' => $cart->id,
                'guest_email' => $guestEmail ?? $cart->guest_email,
                'guest_name' => $guestName,
                'subtotal' => $subtotal,
                'total' => $total,
                'currency' => 'EUR',
                'status' => 'pending',
                // RGPD: Enregistrement du consentement CGV
                'cgv_accepted' => true,
                'cgv_accepted_at' => now(),
                'cgv_version' => '1.0',
                'consent_ip' => $consentIp,
            ]);

            // Create order items from validated items
            foreach ($validItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'photo_id' => $item->photo_id,
                    'product_type' => $item->product_type ?? 'digital',
                    'photo_title' => $item->photo->title,
                    'gallery_title' => $item->photo->gallery?->title,
                    'price' => $item->price,
                ]);
            }

            // NOTE: Cart is NOT marked as converted here.
            // It will be marked as converted only after successful payment
            // in the completeOrder() method.

            return $order->load('items');
        });
    }

    /**
     * Initiate payment for an order
     */
    public function initiatePayment(Order $order): array
    {
        if (! $order->isPending()) {
            throw new \Exception('Cette commande ne peut plus être payée.');
        }

        try {
            // If order already has a checkout, verify it's still valid and reuse it
            if ($order->sumup_checkout_id) {
                try {
                    $existingCheckout = $this->sumUpService->getCheckout($order->sumup_checkout_id);

                    // If checkout is still pending, reuse it
                    if (in_array($existingCheckout['status'] ?? '', ['PENDING', 'NEW'])) {
                        Log::info('Reusing existing SumUp checkout', [
                            'order_id' => $order->id,
                            'checkout_id' => $order->sumup_checkout_id,
                        ]);

                        return [
                            'checkout_id' => $order->sumup_checkout_id,
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                        ];
                    }

                    // If checkout is paid, complete the order
                    if ($existingCheckout['status'] === 'PAID') {
                        $this->completeOrder($order, $existingCheckout['transaction_id'] ?? $order->sumup_checkout_id);
                        throw new \Exception('Cette commande a déjà été payée.');
                    }

                    // If checkout failed or expired, deactivate it and create new one
                    $this->sumUpService->deactivateCheckout($order->sumup_checkout_id);
                    $order->update(['sumup_checkout_id' => null]);
                } catch (\Exception $e) {
                    // If we can't get the checkout, it might be expired - create a new one
                    Log::warning('Could not reuse existing checkout, creating new one', [
                        'order_id' => $order->id,
                        'checkout_id' => $order->sumup_checkout_id,
                        'error' => $e->getMessage(),
                    ]);
                    $order->update(['sumup_checkout_id' => null]);
                }
            }

            $checkout = $this->sumUpService->createCheckout($order);

            // Create or update payment record
            $existingPayment = Payment::where('order_id', $order->id)->first();
            if ($existingPayment) {
                $existingPayment->update([
                    'provider_payment_id' => $checkout['id'],
                    'status' => 'pending',
                ]);
            } else {
                Payment::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'provider' => 'sumup',
                    'provider_payment_id' => $checkout['id'],
                    'amount' => $order->total,
                    'currency' => $order->currency,
                    'type' => 'photo_purchase',
                    'status' => 'pending',
                ]);
            }

            return [
                'checkout_id' => $checkout['id'],
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ];
        } catch (\Exception $e) {
            Log::error('Order payment initiation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Complete an order after successful payment
     */
    public function completeOrder(Order $order, string $transactionId): Order
    {
        // DB transaction: atomic state changes only
        $order = DB::transaction(function () use ($order, $transactionId) {
            // Lock order to prevent concurrent completion (webhook + polling race)
            $order = Order::lockForUpdate()->find($order->id);

            if ($order->isPaid()) {
                return $order->load('items.photo'); // Idempotent: already completed
            }

            if (! $order->isPending()) {
                throw new \Exception('Commande dans un état inattendu.');
            }

            $order->markAsPaid($transactionId);

            // Lock and update payment record
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->first();
            $payment?->update([
                'status' => 'completed',
                'provider_payment_id' => $transactionId,
            ]);

            // Mark the cart as converted (payment successful)
            if ($order->cart_id) {
                $cart = Cart::find($order->cart_id);
                $cart?->markAsConverted();
            }

            // Generate download token
            $order->generateDownloadToken();

            return $order->fresh(['items.photo']);
        });

        // Side-effects outside transaction: invoice generation + emails
        if ($order->isPaid()) {
            $order->load('items');

            // Generate invoice PDF (idempotent)
            $invoice = null;
            try {
                $invoice = $this->invoiceService->generateForOrder($order);
            } catch (\Exception $e) {
                Log::error('Failed to generate invoice', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->sendOrderConfirmationEmail($order, $invoice);

            if ($order->hasPrintItems()) {
                $this->sendPrintOrderNotification($order);
            }
        }

        return $order;
    }

    /**
     * Handle failed payment
     */
    public function handleFailedPayment(Order $order): Order
    {
        $order->markAsFailed();

        $order->payment?->update(['status' => 'failed']);

        return $order;
    }

    /**
     * Verify checkout status and update order accordingly
     */
    public function verifyAndUpdateOrder(string $checkoutId): Order
    {
        $order = Order::where('sumup_checkout_id', $checkoutId)->firstOrFail();

        if ($order->isPaid()) {
            return $order;
        }

        $checkout = $this->sumUpService->getCheckout($checkoutId);

        switch ($checkout['status']) {
            case 'PAID':
                return $this->completeOrder($order, $checkout['transaction_id'] ?? $checkoutId);

            case 'FAILED':
                return $this->handleFailedPayment($order);

            default:
                // Still pending
                return $order;
        }
    }

    /**
     * Get orders for a user
     */
    public function getOrdersForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Order::forUser($user->id)
            ->with('items.photo')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get orders for a guest email
     */
    public function getOrdersForEmail(string $email): \Illuminate\Database\Eloquent\Collection
    {
        return Order::forEmail($email)
            ->with('items.photo')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Send order confirmation email (queued with retries)
     */
    private function sendOrderConfirmationEmail(Order $order, $invoice = null): void
    {
        try {
            $email = $order->customer_email;
            if (! $email) {
                return;
            }

            $downloadToken = $order->metadata['download_token'] ?? null;
            $downloadUrl = config('app.frontend_url').'/commande/'.$order->id.'?token='.$downloadToken;

            Mail::to($email)->queue(new OrderConfirmationMail($order, $downloadUrl, $invoice));
        } catch (\Exception $e) {
            Log::error('Failed to queue order confirmation email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification to admin for print orders (queued with retries)
     */
    private function sendPrintOrderNotification(Order $order): void
    {
        try {
            $adminEmail = config('mail.admin_email', config('mail.from.address'));
            if (! $adminEmail) {
                return;
            }

            Mail::to($adminEmail)->queue(new PrintOrderNotificationMail($order));
        } catch (\Exception $e) {
            Log::error('Failed to queue print order notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get order with download access validation
     */
    public function getOrderForDownload(string $orderId, ?string $token = null, ?User $user = null): Order
    {
        $order = Order::with('items.photo')->findOrFail($orderId);

        if (! $order->isPaid()) {
            throw new \Exception('Cette commande n\'a pas été payée.');
        }

        // Check access: user is owner OR valid download token OR recent order
        $hasAccess = false;

        // 1. Authenticated user owns the order
        if ($user && $order->user_id === $user->id) {
            $hasAccess = true;
        }
        // 2. Valid download token
        elseif ($token && $order->isDownloadTokenValid($token)) {
            $hasAccess = true;
        }
        // 3. Order was paid recently (within 30 minutes) - allows immediate download after payment
        elseif ($order->paid_at && $order->paid_at->diffInMinutes(now()) < 30) {
            $hasAccess = true;
        }

        if (! $hasAccess) {
            throw new \Exception('Accès non autorisé à cette commande.');
        }

        return $order;
    }
}
