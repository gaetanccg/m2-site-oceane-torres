<?php

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Daily cleanup: expire stale carts and abandoned orders
Schedule::call(function () {
    // Expire active carts older than 7 days
    $expiredCarts = Cart::where('status', 'active')
        ->where('expires_at', '<', now())
        ->update(['status' => 'expired']);

    // Expire pending orders older than 24 hours (abandoned checkouts)
    $expiredOrders = Order::where('status', 'pending')
        ->where('created_at', '<', now()->subHours(24))
        ->update(['status' => 'expired']);

    if ($expiredCarts > 0 || $expiredOrders > 0) {
        Log::info('Scheduled cleanup completed', [
            'expired_carts' => $expiredCarts,
            'expired_orders' => $expiredOrders,
        ]);
    }
})->daily()->at('03:00')->name('cleanup-stale-carts-orders');
