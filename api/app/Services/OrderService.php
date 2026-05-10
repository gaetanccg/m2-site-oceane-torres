<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Mail\OrderConfirmationMail;
use App\Mail\PrintOrderNotificationMail;
use App\Mail\SchoolOrderConfirmationMail;
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
     *
     * @param  array<string,string|null>  $shippingData  Champs shipping_phone, shipping_address_line1/2,
     *                                                   shipping_postal_code, shipping_city, shipping_country
     */
    public function createFromCart(
        Cart $cart,
        ?User $user = null,
        ?string $guestEmail = null,
        ?string $guestFirstName = null,
        ?string $guestLastName = null,
        ?string $consentIp = null,
        array $shippingData = []
    ): Order {
        $existingOrder = $this->findReusablePendingOrder($cart);
        if ($existingOrder) {
            $this->applyShippingDataToOrder($existingOrder, $shippingData);
            $this->persistShippingAddressOnUser($user, $shippingData);

            return $existingOrder;
        }

        if ($cart->items->isEmpty()) {
            throw new BusinessException('Le panier est vide.', 400);
        }

        return DB::transaction(function () use ($cart, $user, $guestEmail, $guestFirstName, $guestLastName, $consentIp, $shippingData) {
            $cart->load('items.photo.gallery.galleryProductTypes.packTiers');

            $resolvedPrices = $this->resolvePackPrices($cart);
            $validItems = $this->validateCartItems($cart, $resolvedPrices);

            if (empty($validItems)) {
                throw new BusinessException('Le panier ne contient aucun article valide.', 400);
            }

            $subtotal = (float) collect($validItems)->sum('price');
            $hasPrints = collect($validItems)->contains(fn ($item) => CartItem::isPrintType($item->product_type ?? 'digital'));
            $shippingFee = $hasPrints ? (float) config('shop.shipping_fee_print', 0) : 0.0;

            // Garde-fou serveur : validation a normalement déjà bloqué mais on défend en profondeur
            if ($hasPrints && empty($shippingData['shipping_address_line1'])) {
                throw new BusinessException('Adresse de livraison manquante pour une commande avec tirages.', 422);
            }

            $order = Order::create([
                'user_id' => $user?->id,
                'cart_id' => $cart->id,
                'guest_email' => $guestEmail ?? $cart->guest_email,
                'guest_first_name' => $guestFirstName,
                'guest_last_name' => $guestLastName,
                'shipping_phone' => $hasPrints ? ($shippingData['shipping_phone'] ?? null) : null,
                'shipping_address_line1' => $hasPrints ? ($shippingData['shipping_address_line1'] ?? null) : null,
                'shipping_address_line2' => $hasPrints ? ($shippingData['shipping_address_line2'] ?? null) : null,
                'shipping_postal_code' => $hasPrints ? ($shippingData['shipping_postal_code'] ?? null) : null,
                'shipping_city' => $hasPrints ? ($shippingData['shipping_city'] ?? null) : null,
                'shipping_country' => $hasPrints ? ($shippingData['shipping_country'] ?? 'FR') : null,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total' => $subtotal + $shippingFee,
                'currency' => 'EUR',
                'status' => 'pending',
                'cgv_accepted' => true,
                'cgv_accepted_at' => now(),
                'cgv_version' => '1.0',
                'consent_ip' => $consentIp,
            ]);

            foreach ($validItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'photo_id' => $item->photo_id,
                    'product_type' => $item->product_type ?? 'digital',
                    'quantity' => (int) ($item->quantity ?? 1),
                    'photo_title' => $item->photo->title,
                    'gallery_title' => $item->photo->gallery?->title,
                    'price' => $item->price,
                ]);
            }

            if ($hasPrints) {
                $this->persistShippingAddressOnUser($user, $shippingData);
            }

            return $order->load('items');
        });
    }

    /**
     * Met à jour les champs shipping d'une commande pending réutilisée.
     */
    private function applyShippingDataToOrder(Order $order, array $shippingData): void
    {
        if ((float) $order->shipping_fee <= 0) {
            return;
        }

        $updates = array_filter([
            'shipping_phone' => $shippingData['shipping_phone'] ?? null,
            'shipping_address_line1' => $shippingData['shipping_address_line1'] ?? null,
            'shipping_address_line2' => $shippingData['shipping_address_line2'] ?? null,
            'shipping_postal_code' => $shippingData['shipping_postal_code'] ?? null,
            'shipping_city' => $shippingData['shipping_city'] ?? null,
            'shipping_country' => $shippingData['shipping_country'] ?? 'FR',
        ], fn ($v) => $v !== null);

        if (! empty($updates)) {
            $order->update($updates);
        }
    }

    /**
     * Sauvegarde l'adresse de livraison sur le compte user (pour réutilisation future).
     */
    private function persistShippingAddressOnUser(?User $user, array $shippingData): void
    {
        if (! $user || empty($shippingData['shipping_address_line1'])) {
            return;
        }

        $user->update([
            'phone' => $shippingData['shipping_phone'] ?? $user->phone,
            'address_line1' => $shippingData['shipping_address_line1'],
            'address_line2' => $shippingData['shipping_address_line2'] ?? null,
            'postal_code' => $shippingData['shipping_postal_code'] ?? $user->postal_code,
            'city' => $shippingData['shipping_city'] ?? $user->city,
        ]);
    }

    /**
     * Find an existing pending order that still matches the cart, or expire stale ones.
     */
    private function findReusablePendingOrder(Cart $cart): ?Order
    {
        $existingOrder = Order::where('cart_id', $cart->id)
            ->where('status', 'pending')
            ->with('items')
            ->first();

        if (! $existingOrder) {
            return null;
        }

        $cart->load('items.photo.gallery.galleryProductTypes.packTiers');

        $currentCartTotal = $this->calculateCartTotal($cart);
        $cartPhotoIds = $cart->items->pluck('photo_id')->sort()->values()->toArray();
        $orderPhotoIds = $existingOrder->items->pluck('photo_id')->sort()->values()->toArray();

        $cartChanged = $cart->items->count() !== $existingOrder->items->count()
            || $cartPhotoIds !== $orderPhotoIds
            || abs((float) $existingOrder->total - $currentCartTotal) > 0.01;

        if ($cartChanged) {
            Log::info('Cart changed since order creation, cancelling stale order', [
                'order_id' => $existingOrder->id,
                'order_total' => $existingOrder->total,
                'cart_total' => $currentCartTotal,
            ]);
            $existingOrder->update(['status' => 'expired']);

            return null;
        }

        Log::info('Returning existing pending order', [
            'order_id' => $existingOrder->id,
            'cart_id' => $cart->id,
        ]);

        return $existingOrder;
    }

    /**
     * Calculate total cart price using pack pricing rules (inclut les frais de port).
     */
    private function calculateCartTotal(Cart $cart): float
    {
        $cartService = app(CartService::class);
        $packGroups = $cartService->buildPackGroups($cart);
        $subtotal = 0;
        $hasPrints = false;

        foreach ($packGroups as $group) {
            $first = $group['items']->first();
            $gallery = $first->photo->gallery;
            $productType = $first->product_type;
            $quantity = $group['count'];

            $unitPrice = $gallery?->resolvePackPrice($productType, $quantity)
                ?? $gallery?->getPriceForProductType($productType)
                ?? CartItem::getPriceForType($productType);

            $subtotal += $unitPrice * $quantity;

            if (CartItem::isPrintType($productType)) {
                $hasPrints = true;
            }
        }

        $shippingFee = $hasPrints ? (float) config('shop.shipping_fee_print', 0) : 0.0;

        return $subtotal + $shippingFee;
    }

    /**
     * Build a map of item_id → resolved unit price using cumulative pack quantities.
     */
    private function resolvePackPrices(Cart $cart): array
    {
        $cartService = app(CartService::class);
        $packGroups = $cartService->buildPackGroups($cart);
        $resolvedPrices = [];

        foreach ($packGroups as $group) {
            $first = $group['items']->first();
            $gallery = $first->photo->gallery;
            $productType = $first->product_type;
            $quantity = $group['count'];

            $unitPrice = $gallery?->resolvePackPrice($productType, $quantity)
                ?? $gallery?->getPriceForProductType($productType)
                ?? CartItem::getPriceForType($productType);

            foreach ($group['items'] as $item) {
                $resolvedPrices[$item->id] = $unitPrice;
            }
        }

        return $resolvedPrices;
    }

    /**
     * Validate cart items and update prices. Returns only valid items.
     */
    private function validateCartItems(Cart $cart, array $resolvedPrices): array
    {
        $validItems = [];

        foreach ($cart->items as $item) {
            if (! $item->photo) {
                Log::warning('Cart item photo no longer exists, skipping', ['cart_item_id' => $item->id]);

                continue;
            }

            if (! $item->photo->is_purchasable) {
                Log::warning('Cart item photo no longer purchasable, skipping', [
                    'cart_item_id' => $item->id,
                    'photo_id' => $item->photo_id,
                ]);

                continue;
            }

            $productType = $item->product_type ?? 'digital';
            if (! array_key_exists($productType, CartItem::PRODUCT_TYPES)) {
                $productType = 'digital';
            }

            $currentPrice = $resolvedPrices[$item->id]
                ?? $item->photo->gallery?->getPriceForProductType($productType)
                ?? CartItem::getPriceForType($productType);

            if ((float) $item->price !== $currentPrice) {
                $item->update(['price' => $currentPrice]);
                $item->price = $currentPrice;
            }

            $validItems[] = $item;
        }

        return $validItems;
    }

    /**
     * Initiate payment for an order
     */
    public function initiatePayment(Order $order): array
    {
        if (! $order->isPending()) {
            throw new BusinessException('Cette commande ne peut plus être payée.', 400);
        }

        try {
            // If order already has a checkout, verify it's still valid and reuse it
            if ($order->sumup_checkout_id) {
                $shouldCreateNew = false;

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

                    // If checkout is paid, complete the order and stop
                    if ($existingCheckout['status'] === 'PAID') {
                        $this->completeOrder($order, $existingCheckout['transaction_id'] ?? $order->sumup_checkout_id);
                        throw new BusinessException('Cette commande a déjà été payée.', 409);
                    }

                    // If checkout failed or expired, create new one
                    $shouldCreateNew = true;
                    try {
                        $this->sumUpService->deactivateCheckout($order->sumup_checkout_id);
                    } catch (\Exception) {
                        // Deactivation may fail on FAILED checkouts, that's fine
                    }
                    $order->update(['sumup_checkout_id' => null]);
                } catch (\Exception $e) {
                    // Re-throw "already paid" — don't swallow it
                    if (str_contains($e->getMessage(), 'déjà été payée')) {
                        throw $e;
                    }

                    // For other errors (API timeout, etc.), create a new checkout
                    Log::warning('Could not reuse existing checkout, creating new one', [
                        'order_id' => $order->id,
                        'checkout_id' => $order->sumup_checkout_id,
                        'error' => $e->getMessage(),
                    ]);
                    $shouldCreateNew = true;
                    $order->update(['sumup_checkout_id' => null]);
                }

                // Guard: if order was just completed, don't create a new checkout
                $order->refresh();
                if ($order->isPaid()) {
                    throw new BusinessException('Cette commande a déjà été payée.', 409);
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
        $justCompleted = false;

        $order = DB::transaction(function () use ($order, $transactionId, &$justCompleted) {
            // Lock order to prevent concurrent completion (webhook + polling race)
            $order = Order::lockForUpdate()->find($order->id);

            if ($order->isPaid()) {
                return $order->load('items.photo'); // Idempotent: already completed
            }

            if (! $order->isPending() && ! $order->isFailed()) {
                throw new BusinessException('Commande dans un état inattendu.', 409);
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

            $justCompleted = true;

            return $order->fresh(['items.photo']);
        });

        // Side-effects outside transaction — only if THIS request completed the order
        // Prevents duplicate emails when webhook + polling race
        if ($justCompleted && $order->isPaid()) {
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

            // School orders are handled separately via the school session admin view —
            // skip the generic print notification for them.
            if ($order->hasPrintItems() && ! $this->isSchoolOrder($order)) {
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

        // Sandbox: auto-complete since SumUp sandbox never transitions to PAID
        // Double-check app environment to prevent accidental free orders in production
        if (config('sumup.environment') === 'sandbox' && app()->environment('local', 'testing')) {
            return $this->completeOrder($order, 'sandbox_'.time());
        }

        $checkout = $this->sumUpService->getCheckout($checkoutId);

        switch ($checkout['status']) {
            case 'PAID':
                return $this->completeOrder($order, $checkout['transaction_id'] ?? $checkoutId);

            default:
                // Don't mark as failed here — the widget may still allow retries.
                // Only the webhook should mark orders as definitively failed.
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
            ->limit(100)
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
            ->limit(100)
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

            // School orders get a dedicated mail (no download section, school-specific message)
            if ($this->isSchoolOrder($order)) {
                Mail::to($email)->queue(new SchoolOrderConfirmationMail($order, $invoice));

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

    private function isSchoolOrder(Order $order): bool
    {
        return $order->items->isNotEmpty()
            && $order->items->every(fn ($item) => $item->product_type === 'print_scolaire');
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
            throw new BusinessException('Cette commande n\'a pas été payée.', 403);
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
            throw new BusinessException('Accès non autorisé à cette commande.', 403);
        }

        return $order;
    }
}
