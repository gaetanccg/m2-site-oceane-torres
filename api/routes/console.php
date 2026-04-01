<?php

use App\Models\Cart;
use App\Models\ContactMessage;
use App\Models\DownloadLog;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
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

// Weekly RGPD cleanup: purge old personal data
Schedule::call(function () {
    // Delete expired carts older than 30 days (personal data: guest_email)
    $deletedCarts = Cart::where('status', 'expired')
        ->where('updated_at', '<', now()->subDays(30))
        ->delete();

    // Anonymize expired/failed orders older than 6 months
    $anonymizedOrders = Order::whereIn('status', ['expired', 'failed'])
        ->where('created_at', '<', now()->subMonths(6))
        ->whereNotNull('guest_email')
        ->update([
            'guest_email' => null,
            'guest_first_name' => null,
            'guest_last_name' => null,
        ]);

    // Delete contact messages older than 12 months
    $deletedMessages = ContactMessage::where('created_at', '<', now()->subMonths(12))
        ->delete();

    // Delete download logs older than 12 months (IP, user agent)
    $deletedLogs = DownloadLog::where('downloaded_at', '<', now()->subMonths(12))
        ->delete();

    // Purge expired Sanctum tokens
    $deletedTokens = DB::table('personal_access_tokens')
        ->where('last_used_at', '<', now()->subMonths(6))
        ->orWhere(function ($query) {
            $query->whereNull('last_used_at')
                ->where('created_at', '<', now()->subMonths(3));
        })
        ->delete();

    $total = $deletedCarts + $anonymizedOrders + $deletedMessages + $deletedLogs + $deletedTokens;

    if ($total > 0) {
        Log::info('RGPD weekly cleanup completed', [
            'deleted_carts' => $deletedCarts,
            'anonymized_orders' => $anonymizedOrders,
            'deleted_contact_messages' => $deletedMessages,
            'deleted_download_logs' => $deletedLogs,
            'deleted_tokens' => $deletedTokens,
        ]);
    }
})->weekly()->sundays()->at('04:00')->name('rgpd-data-retention-cleanup');
