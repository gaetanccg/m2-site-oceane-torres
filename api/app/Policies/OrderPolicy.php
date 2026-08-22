<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderAccessSignature;

/**
 * Seul endroit qui décide qui accède à une commande. La règle vivait auparavant en deux
 * exemplaires divergents — OrderController::show autorisait sur `created_at`,
 * OrderService::getOrderForDownload sur `paid_at` — et aucun des deux ne passait par
 * le Gate.
 */
class OrderPolicy
{
    public function view(?User $user, Order $order, ?string $token = null, ?string $email = null): bool
    {
        if ($this->owns($user, $order)) {
            return true;
        }

        if ($email !== null && $order->guest_email && hash_equals($order->guest_email, $email)) {
            return true;
        }

        return $this->holdsValidToken($order, $token);
    }

    public function download(?User $user, Order $order, ?string $token = null): bool
    {
        if (! $order->isPaid()) {
            return false;
        }

        return $this->owns($user, $order) || $this->holdsValidToken($order, $token);
    }

    private function owns(?User $user, Order $order): bool
    {
        return $user !== null && $order->user_id === $user->id;
    }

    private function holdsValidToken(Order $order, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return $order->isDownloadTokenValid($token) || OrderAccessSignature::verify($order, $token);
    }
}
