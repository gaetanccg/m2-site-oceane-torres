<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * User can view order if they own it, or have a valid download token,
     * or order was created recently (within 30 minutes).
     */
    public function view(?User $user, Order $order, ?string $token = null, ?string $email = null): bool
    {
        // Authenticated user owns the order
        if ($user && $order->user_id === $user->id) {
            return true;
        }

        // Guest email matches
        if ($order->guest_email && $email === $order->guest_email) {
            return true;
        }

        // Valid download token
        if ($token && $order->isDownloadTokenValid($token)) {
            return true;
        }

        // Recently created (within 30 minutes)
        if ($order->created_at->diffInMinutes(now()) < 30) {
            return true;
        }

        return false;
    }

    /**
     * User can download if order is paid and they have access.
     */
    public function download(?User $user, Order $order, ?string $token = null): bool
    {
        if (! $order->isPaid()) {
            return false;
        }

        // Authenticated user owns the order
        if ($user && $order->user_id === $user->id) {
            return true;
        }

        // Valid download token
        if ($token && $order->isDownloadTokenValid($token)) {
            return true;
        }

        // Recently paid (within 30 minutes)
        if ($order->paid_at && $order->paid_at->diffInMinutes(now()) < 30) {
            return true;
        }

        return false;
    }
}
