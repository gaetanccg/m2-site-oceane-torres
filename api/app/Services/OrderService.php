<?php

namespace App\Services;

use App\Models\Cart;
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

    public function __construct(SumUpService $sumUpService)
    {
        $this->sumUpService = $sumUpService;
    }

    /**
     * Create an order from a cart (or return existing pending order)
     */
    public function createFromCart(Cart $cart, ?User $user = null, ?string $guestEmail = null, ?string $guestName = null): Order
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

        return DB::transaction(function () use ($cart, $user, $guestEmail, $guestName) {
            $cart->load('items.photo.gallery');

            $subtotal = $cart->items->sum('price');
            $total = $subtotal; // No tax for now

            $order = Order::create([
                'user_id' => $user?->id,
                'cart_id' => $cart->id,
                'guest_email' => $guestEmail ?? $cart->guest_email,
                'guest_name' => $guestName,
                'subtotal' => $subtotal,
                'total' => $total,
                'currency' => config('sumup.photo.currency', 'EUR'),
                'status' => 'pending',
            ]);

            // Create order items
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'photo_id' => $item->photo_id,
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
            $checkout = $this->sumUpService->createCheckout($order);

            // Create payment record
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
        return DB::transaction(function () use ($order, $transactionId) {
            $order->markAsPaid($transactionId);

            // Update payment record
            $order->payment?->update([
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

            // Send confirmation email
            $this->sendOrderConfirmationEmail($order);

            return $order->fresh(['items.photo']);
        });
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
     * Send order confirmation email
     */
    private function sendOrderConfirmationEmail(Order $order): void
    {
        try {
            $email = $order->customer_email;
            if (! $email) {
                return;
            }

            $downloadToken = $order->metadata['download_token'] ?? null;
            $downloadUrl = config('app.frontend_url') . '/commande/' . $order->id . '?token=' . $downloadToken;

            Mail::send('emails.order-confirmation', [
                'order' => $order,
                'downloadUrl' => $downloadUrl,
            ], function ($message) use ($email, $order) {
                $message->to($email)
                    ->subject('Confirmation de commande - ' . $order->order_number);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
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
