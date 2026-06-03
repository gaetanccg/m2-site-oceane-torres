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
     * @param  array<string,string|null>  $shippingData
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
        // Politique : chaque tentative de checkout repart d'un order vierge — pas de réutilisation
        // pour éviter les stale states (widget figé sur ancien prix, cart modifié entre temps).
        $this->expireAllPendingOrders($cart, $user);

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

            // sum('price') ignorait la quantité → sous-facturation SumUp.
            $subtotal = collect($validItems)->sum(
                fn ($item) => (float) $item->price * (int) ($item->quantity ?? 1)
            );
            $requiresShipping = collect($validItems)->contains(function ($item) {
                $gallery = $item->photo?->gallery;
                $productType = $item->product_type ?? 'digital';

                return $gallery
                    ? $gallery->getRequiresShippingForProductType($productType)
                    : CartItem::requiresShipping($productType);
            });
            $shippingFee = $requiresShipping ? (float) config('shop.shipping_fee_print', 0) : 0.0;

            // Garde-fou : la FormRequest valide déjà, defense in depth.
            if ($requiresShipping && empty($shippingData['shipping_address_line1'])) {
                throw new BusinessException('Adresse de livraison manquante pour une commande avec tirages.', 422);
            }

            $order = Order::create([
                'user_id' => $user?->id,
                'cart_id' => $cart->id,
                'guest_email' => $guestEmail ?? $user?->email ?? $cart->guest_email,
                'guest_first_name' => $guestFirstName ?? $user?->first_name,
                'guest_last_name' => $guestLastName ?? $user?->last_name,
                'shipping_phone' => $requiresShipping ? ($shippingData['shipping_phone'] ?? null) : null,
                'shipping_address_line1' => $requiresShipping ? ($shippingData['shipping_address_line1'] ?? null) : null,
                'shipping_address_line2' => $requiresShipping ? ($shippingData['shipping_address_line2'] ?? null) : null,
                'shipping_postal_code' => $requiresShipping ? ($shippingData['shipping_postal_code'] ?? null) : null,
                'shipping_city' => $requiresShipping ? ($shippingData['shipping_city'] ?? null) : null,
                // shipping_country est NOT NULL en DB → 'FR' par défaut (inoffensif sur commandes digitales).
                'shipping_country' => $shippingData['shipping_country'] ?? 'FR',
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

            if ($requiresShipping) {
                $this->persistShippingAddressOnUser($user, $shippingData);
            }

            return $order->load('items');
        });
    }

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
     * Garantit qu'aucun ancien widget SumUp ne puisse être payé après évolution du panier :
     * désactive les checkouts SumUp et passe les orders en `expired`.
     */
    private function expireAllPendingOrders(Cart $cart, ?User $user): void
    {
        $query = Order::where('status', 'pending');

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereIn('cart_id', Cart::where('session_id', $cart->session_id)
                ->pluck('id'));
        }

        $pendingOrders = $query->get(['id', 'sumup_checkout_id']);

        if ($pendingOrders->isEmpty()) {
            return;
        }

        foreach ($pendingOrders as $order) {
            if ($order->sumup_checkout_id) {
                try {
                    $this->sumUpService->deactivateCheckout($order->sumup_checkout_id);
                } catch (\Exception $e) {
                    Log::warning('SumUp deactivateCheckout failed during expiration', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Garde-fou contre la race : entre le `get()` ci-dessus et cet `update()`,
        // un webhook SumUp peut être arrivé et avoir fait passer un order à `paid`.
        // Sans la clause `where('status', 'pending')`, on écraserait silencieusement
        // un paiement valide en `expired`.
        $affected = Order::whereIn('id', $pendingOrders->pluck('id'))
            ->where('status', 'pending')
            ->update([
                'status' => 'expired',
                'sumup_checkout_id' => null,
            ]);

        Log::info('Expired all pending orders before new checkout', [
            'cart_id' => $cart->id,
            'user_id' => $user?->id,
            'candidates' => $pendingOrders->count(),
            'expired_count' => $affected,
        ]);
    }

    /** @return array<string,float> item_id → unit price (applies pack tiers). */
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

    public function initiatePayment(Order $order): array
    {
        if (! $order->isPending()) {
            throw new BusinessException('Cette commande ne peut plus être payée.', 400);
        }

        try {
            if ($order->sumup_checkout_id) {
                $shouldCreateNew = false;

                try {
                    $existingCheckout = $this->sumUpService->getCheckout($order->sumup_checkout_id);

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

                    if ($existingCheckout['status'] === 'PAID') {
                        $this->completeOrder($order, $existingCheckout['transaction_id'] ?? $order->sumup_checkout_id);
                        throw new BusinessException('Cette commande a déjà été payée.', 409);
                    }

                    $shouldCreateNew = true;
                    try {
                        $this->sumUpService->deactivateCheckout($order->sumup_checkout_id);
                    } catch (\Exception) {
                        // deactivate échoue sur un checkout FAILED — acceptable
                    }
                    $order->update(['sumup_checkout_id' => null]);
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'déjà été payée')) {
                        throw $e;
                    }

                    Log::warning('Could not reuse existing checkout, creating new one', [
                        'order_id' => $order->id,
                        'checkout_id' => $order->sumup_checkout_id,
                        'error' => $e->getMessage(),
                    ]);
                    $shouldCreateNew = true;
                    $order->update(['sumup_checkout_id' => null]);
                }

                $order->refresh();
                if ($order->isPaid()) {
                    throw new BusinessException('Cette commande a déjà été payée.', 409);
                }
            }

            $checkout = $this->sumUpService->createCheckout($order);

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

    public function completeOrder(Order $order, string $transactionId): Order
    {
        $justCompleted = false;

        $order = DB::transaction(function () use ($order, $transactionId, &$justCompleted) {
            // Lock pour empêcher complétion concurrente (race webhook + polling).
            $order = Order::lockForUpdate()->find($order->id);

            if ($order->isPaid()) {
                return $order->load('items.photo');
            }

            if (! $order->isPending() && ! $order->isFailed()) {
                throw new BusinessException('Commande dans un état inattendu.', 409);
            }

            $order->markAsPaid($transactionId);

            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->first();
            $payment?->update([
                'status' => 'completed',
                'provider_payment_id' => $transactionId,
            ]);

            if ($order->cart_id) {
                $cart = Cart::find($order->cart_id);
                $cart?->markAsConverted();
            }

            $order->generateDownloadToken();

            $justCompleted = true;

            return $order->fresh(['items.photo']);
        });

        // Side-effects hors transaction, et uniquement si CE request a complété l'order
        // (sinon double email quand webhook + polling se déclenchent ensemble).
        if ($justCompleted && $order->isPaid()) {
            $order->load('items');

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

            // Les school orders ont leur propre vue admin → on skip la notif print générique.
            if ($order->hasPrintItems() && ! $this->isSchoolOrder($order)) {
                $this->sendPrintOrderNotification($order);
            }
        }

        return $order;
    }

    public function handleFailedPayment(Order $order): Order
    {
        $order->markAsFailed();

        $order->payment?->update(['status' => 'failed']);

        return $order;
    }

    public function verifyAndUpdateOrder(string $checkoutId): Order
    {
        $order = Order::where('sumup_checkout_id', $checkoutId)->firstOrFail();

        if ($order->isPaid()) {
            return $order;
        }

        // Sandbox SumUp ne passe jamais en PAID → auto-complete. Double-check env pour
        // ne pas finaliser des commandes gratuites en prod.
        if (config('sumup.environment') === 'sandbox' && app()->environment('local', 'testing')) {
            return $this->completeOrder($order, 'sandbox_'.time());
        }

        $checkout = $this->sumUpService->getCheckout($checkoutId);

        switch ($checkout['status']) {
            case 'PAID':
                return $this->completeOrder($order, $checkout['transaction_id'] ?? $checkoutId);

            default:
                // Don't mark as failed here — the widget may still allow retries.
                // Only the webhook (or reconcilePendingOrders) should mark orders
                // as definitively failed.
                return $order;
        }
    }

    /**
     * Réconciliation périodique des orders pending : filet de sécurité au cas où
     * le webhook SumUp serait perdu (5xx persistant côté nous épuisant les retries,
     * webhook non envoyé, etc.).
     *
     * On regarde les orders `pending` avec un `sumup_checkout_id`, créées il y a :
     *  - assez longtemps pour avoir laissé le webhook + ses retries arriver
     *    naturellement (>= $minMinutesAgo) — évite de bruiter le flux normal ;
     *  - pas trop longtemps pour ne pas ratisser des historiques abandonnés
     *    (<= $maxHoursAgo) — `console.php` les passe à `expired` à 24 h de toute façon.
     */
    public function reconcilePendingOrders(int $minMinutesAgo = 15, int $maxHoursAgo = 24): array
    {
        $cutoffNew = now()->subMinutes($minMinutesAgo);
        $cutoffOld = now()->subHours($maxHoursAgo);

        $candidates = Order::where('status', 'pending')
            ->whereNotNull('sumup_checkout_id')
            ->where('created_at', '<', $cutoffNew)
            ->where('created_at', '>', $cutoffOld)
            ->get(['id', 'sumup_checkout_id']);

        $stats = [
            'candidates' => $candidates->count(),
            'paid' => 0,
            'failed' => 0,
            'still_pending' => 0,
            'errors' => 0,
        ];

        if ($candidates->isEmpty()) {
            return $stats;
        }

        foreach ($candidates as $candidate) {
            try {
                $checkout = $this->sumUpService->getCheckout($candidate->sumup_checkout_id);
                $verifiedStatus = $checkout['status'] ?? null;

                if ($verifiedStatus === 'PAID') {
                    // Recharge frais (lockForUpdate dans completeOrder gère les races)
                    $order = Order::find($candidate->id);
                    if ($order) {
                        $this->completeOrder($order, $checkout['transaction_id'] ?? $candidate->sumup_checkout_id);
                        $stats['paid']++;
                    }
                } elseif ($verifiedStatus === 'FAILED') {
                    $order = Order::find($candidate->id);
                    if ($order && $order->isPending()) {
                        $this->handleFailedPayment($order);
                        $stats['failed']++;
                    }
                } else {
                    // PENDING ou EXPIRED côté SumUp — on n'agit pas, le cleanup
                    // quotidien (console.php) se chargera des vraiment vieilles.
                    $stats['still_pending']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning('Reconciliation failed for order', [
                    'order_id' => $candidate->id,
                    'checkout_id' => $candidate->sumup_checkout_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Reconciliation completed', $stats);

        return $stats;
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

    public function getOrdersForEmail(string $email): \Illuminate\Database\Eloquent\Collection
    {
        return Order::forEmail($email)
            ->with('items.photo')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }

    private function sendOrderConfirmationEmail(Order $order, $invoice = null): void
    {
        try {
            $email = $order->customer_email;
            if (! $email) {
                return;
            }

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

    public function getOrderForDownload(string $orderId, ?string $token = null, ?User $user = null): Order
    {
        $order = Order::with('items.photo')->findOrFail($orderId);

        if (! $order->isPaid()) {
            throw new BusinessException('Cette commande n\'a pas été payée.', 403);
        }

        // Accès si : owner authentifié, token download valide, ou paid_at < 30 min
        // (fenêtre courte pour permettre le download immédiat post-paiement).
        $hasAccess = false;

        if ($user && $order->user_id === $user->id) {
            $hasAccess = true;
        } elseif ($token && $order->isDownloadTokenValid($token)) {
            $hasAccess = true;
        } elseif ($order->paid_at && $order->paid_at->diffInMinutes(now()) < 30) {
            $hasAccess = true;
        }

        if (! $hasAccess) {
            throw new BusinessException('Accès non autorisé à cette commande.', 403);
        }

        return $order;
    }
}
