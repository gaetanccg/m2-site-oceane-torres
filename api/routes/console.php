<?php

use App\Models\Cart;
use App\Models\ContactMessage;
use App\Models\DownloadLog;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Reconcile pending orders every 10 minutes — filet de sécurité au cas où
// le webhook SumUp serait perdu (cf. SumUpPaymentController::webhook qui
// retourne 503 sur erreur transient pour profiter des retries SumUp, mais qui
// peut quand même rater définitivement après 1m / 5m / 20m / 2h).
Schedule::call(function () {
    /** @var OrderService $service */
    $service = app(OrderService::class);
    $service->reconcilePendingOrders();
})
    ->everyTenMinutes()
    ->name('reconcile-pending-orders')
    ->withoutOverlapping(10);

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

// Monthly RGPD: purge accounting data (orders/invoices/payments) past the legal
// retention period. Complements the erasure feature — these records are RETAINED
// during the retention window then removed here. Adjust --years to the confirmed
// legal duration (default 10 years, Code de commerce).
Schedule::command('privacy:purge-expired', ['--years' => 10])
    ->monthlyOn(1, '05:00')
    ->name('privacy-purge-expired')
    ->withoutOverlapping();
