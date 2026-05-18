<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EventCategoryController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\SchoolSessionController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingRequestController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EventGalleryController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImageProxyController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PrestationController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\SumUpPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HealthController::class, 'index']);
Route::get('/health', [HealthController::class, 'index']);
Route::get('/health/database', [HealthController::class, 'database']);
Route::get('/health/tables', [HealthController::class, 'tables']);

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Authentication
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Prestations (public)
Route::get('/prestations', [PrestationController::class, 'index']);
Route::get('/prestations/{prestation}', [PrestationController::class, 'show']);

// Galleries (public)
Route::get('/galleries', [GalleryController::class, 'index']);
Route::get('/galleries/{gallery}', [GalleryController::class, 'show']);
Route::get('/galleries/token/{token}', [GalleryController::class, 'showByToken']);
Route::get('/galleries/code/{code}', [GalleryController::class, 'showByShareCode']);
Route::get('/galleries/download/{token}', [GalleryController::class, 'showDownloadableByToken']);
Route::get('/galleries/{gallery}/download-zip', [GalleryController::class, 'downloadZip']);
Route::get('/galleries/{gallery}/download-file', [GalleryController::class, 'downloadFile']);

// Photos (public - watermarked)
Route::get('/photos/{photo}', [PhotoController::class, 'show']);
Route::post('/photos/{photo}/like', [PhotoController::class, 'like']);
Route::get('/photos/{photo}/download', [PhotoController::class, 'download']);

// Image proxy (secure image streaming with watermarks)
Route::middleware('throttle:images')->group(function () {
    Route::get('/images/preview/{photo}', [ImageProxyController::class, 'preview']);
    Route::get('/images/thumbnail/{photo}', [ImageProxyController::class, 'thumbnail']);
    Route::get('/images/clean/{photo}', [ImageProxyController::class, 'clean']);
});
Route::middleware('throttle:downloads')->group(function () {
    Route::get('/images/download/{photo}', [ImageProxyController::class, 'download']);
});

// Contact
Route::post('/contact', [ContactController::class, 'send']);

// Event Galleries (public)
Route::get('/events', [EventGalleryController::class, 'index']);
Route::get('/events/{gallery}', [EventGalleryController::class, 'show']);

// Gift cards (public)
Route::get('/gift-cards/validate/{code}', [GiftCardController::class, 'validate']);

// Booking request (public - sans authentification)
Route::post('/booking-request', [BookingRequestController::class, 'store']);

// Cart (public - works with session for guests)
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::post('/add', [CartController::class, 'addItem']);
    Route::put('/item/{item}/type', [CartController::class, 'updateItemType']);
    Route::put('/item/{item}/quantity', [CartController::class, 'updateItemQuantity']);
    Route::delete('/item/{item}', [CartController::class, 'removeItem']);
    Route::delete('/clear', [CartController::class, 'clear']);
    Route::put('/email', [CartController::class, 'updateEmail']);
});

// Checkout & Orders (public for guest checkout)
Route::post('/checkout', [OrderController::class, 'createFromCart']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::get('/orders/{order}/download/{item}', [OrderController::class, 'downloadPhoto']);
Route::get('/orders/{order}/download-all', [OrderController::class, 'downloadAll']);
Route::get('/orders/{order}/invoice', [OrderController::class, 'downloadInvoice']);
Route::post('/orders/by-email', [OrderController::class, 'getByEmail']);

// SumUp Payment (public)
Route::prefix('payments/sumup')->group(function () {
    Route::get('/config', [SumUpPaymentController::class, 'getConfig']);
    Route::post('/create-checkout', [SumUpPaymentController::class, 'createCheckout']);
    Route::get('/callback', [SumUpPaymentController::class, 'callback']);
    Route::post('/verify', [SumUpPaymentController::class, 'verifyPayment']);
    Route::post('/cancel-checkout', [SumUpPaymentController::class, 'cancelCheckout']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    // Reservations
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update']);
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy']);

    // Gift cards
    Route::post('/gift-cards', [GiftCardController::class, 'store']);
    Route::get('/gift-cards/{giftCard}', [GiftCardController::class, 'show']);

    // User galleries (private)
    Route::get('/my-galleries', [GalleryController::class, 'myGalleries']);
    Route::get('/my-galleries/{gallery}/download', [GalleryController::class, 'downloadPhotos']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Account (client dashboard)
    Route::get('/account/dashboard', [AccountController::class, 'dashboard']);

    // Cart merge (after login)
    Route::post('/cart/merge', [CartController::class, 'merge']);

    // User orders
    Route::get('/orders', [OrderController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Users management
    Route::apiResource('users', UserController::class);

    // Clients management
    Route::apiResource('clients', ClientController::class);
    Route::get('/clients/{client}/reservations', [ClientController::class, 'reservations']);
    Route::post('/clients/{client}/gdpr-export', [ClientController::class, 'gdprExport']);

    // Prestations management
    Route::get('/prestations', [PrestationController::class, 'adminIndex']);
    Route::apiResource('prestations', PrestationController::class)->except(['index', 'show']);
    Route::put('/prestations/{prestation}/toggle', [PrestationController::class, 'toggle']);

    // Reservations management
    Route::get('/reservations', [ReservationController::class, 'adminIndex']);
    Route::get('/reservations/calendar', [ReservationController::class, 'calendar']);
    Route::get('/reservations/{reservation}', [ReservationController::class, 'adminShow']);
    Route::put('/reservations/{reservation}', [ReservationController::class, 'adminUpdate']);
    Route::put('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'adminDestroy']);

    // Availability management
    Route::prefix('availability')->group(function () {
        // Slots
        Route::get('/slots', [AvailabilityController::class, 'indexSlots']);
        Route::get('/slots/available', [AvailabilityController::class, 'availableSlots']);
        Route::post('/slots', [AvailabilityController::class, 'storeSlot']);
        Route::put('/slots/{slot}', [AvailabilityController::class, 'updateSlot']);
        Route::delete('/slots/{slot}', [AvailabilityController::class, 'destroySlot']);

        // Patterns
        Route::get('/patterns', [AvailabilityController::class, 'indexPatterns']);
        Route::post('/patterns', [AvailabilityController::class, 'storePattern']);
        Route::put('/patterns/{pattern}', [AvailabilityController::class, 'updatePattern']);
        Route::delete('/patterns/{pattern}', [AvailabilityController::class, 'destroyPattern']);
        Route::post('/patterns/{pattern}/generate', [AvailabilityController::class, 'generateSlots']);
        Route::put('/patterns/{pattern}/toggle', [AvailabilityController::class, 'togglePattern']);
    });

    // Galleries management
    Route::get('/galleries', [GalleryController::class, 'adminIndex']);
    Route::get('/galleries/{gallery}', [GalleryController::class, 'adminShow']);
    Route::apiResource('galleries', GalleryController::class)->except(['index', 'show']);
    Route::post('/galleries/{gallery}/photos', [PhotoController::class, 'store']);
    Route::post('/galleries/{gallery}/photos/async', [PhotoController::class, 'storeAsync']);
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
    Route::put('/galleries/{gallery}/regenerate-token', [GalleryController::class, 'regenerateToken']);
    Route::post('/galleries/{gallery}/regenerate-code', [GalleryController::class, 'regenerateShareCode']);
    Route::post('/galleries/{gallery}/send-email', [GalleryController::class, 'sendAccessEmail']);
    Route::post('/galleries/{gallery}/send-sms', [GalleryController::class, 'sendAccessSms']);

    // Photos management
    Route::put('/photos/{photo}/toggle-downloadable', [PhotoController::class, 'toggleDownloadable']);
    Route::put('/photos/bulk-downloadable', [PhotoController::class, 'bulkToggleDownloadable']);
    Route::put('/photos/sort-order', [PhotoController::class, 'updateSortOrder']);

    // Event Categories management
    Route::get('/event-categories', [EventCategoryController::class, 'index']);
    Route::post('/event-categories', [EventCategoryController::class, 'store']);
    Route::put('/event-categories/reorder', [EventCategoryController::class, 'reorder']);
    Route::put('/event-categories/{category}', [EventCategoryController::class, 'update']);
    Route::delete('/event-categories/{category}', [EventCategoryController::class, 'destroy']);

    // Event Galleries management
    Route::get('/events', [EventGalleryController::class, 'adminIndex']);
    Route::get('/events/{gallery}', [EventGalleryController::class, 'adminShow']);
    Route::post('/events', [EventGalleryController::class, 'store']);
    Route::put('/events/{gallery}', [EventGalleryController::class, 'update']);
    Route::delete('/events/{gallery}', [EventGalleryController::class, 'destroy']);
    Route::put('/events/{gallery}/thumbnail', [EventGalleryController::class, 'setThumbnail']);
    Route::get('/events/{gallery}/children', [EventGalleryController::class, 'children']);
    Route::post('/events/{gallery}/photos', [PhotoController::class, 'store']);
    Route::post('/events/{gallery}/photos/async', [PhotoController::class, 'storeAsync']);

    // Upload status (for polling)
    Route::get('/upload-status', [PhotoController::class, 'uploadStatus']);

    // Gift cards
    Route::get('/gift-cards', [GiftCardController::class, 'index']);
    Route::put('/gift-cards/{giftCard}', [GiftCardController::class, 'update']);

    // Notifications
    Route::post('/notifications', [NotificationController::class, 'store']);

    // School Sessions management
    Route::get('/school-sessions', [SchoolSessionController::class, 'index']);
    Route::post('/school-sessions', [SchoolSessionController::class, 'store']);
    Route::get('/school-sessions/{schoolSession}', [SchoolSessionController::class, 'show']);
    Route::get('/school-sessions/{schoolSession}/galleries', [SchoolSessionController::class, 'galleries']);
    Route::get('/school-sessions/{schoolSession}/orders', [SchoolSessionController::class, 'orders']);
    Route::put('/school-sessions/{schoolSession}/upload', [SchoolSessionController::class, 'upload']);
    Route::post('/school-sessions/{schoolSession}/process', [SchoolSessionController::class, 'process']);
    Route::post('/school-sessions/{schoolSession}/retry-failed-photos', [SchoolSessionController::class, 'retryFailedPhotos']);
    Route::post('/school-sessions/{schoolSession}/send-messages', [SchoolSessionController::class, 'sendMessages']);
    Route::post('/school-sessions/{schoolSession}/close', [SchoolSessionController::class, 'close']);
    Route::post('/school-sessions/{schoolSession}/reopen', [SchoolSessionController::class, 'reopen']);
    Route::post('/school-sessions/{schoolSession}/exports', [SchoolSessionController::class, 'createExport']);
    Route::get('/school-sessions/{schoolSession}/exports/latest', [SchoolSessionController::class, 'latestExport']);
    Route::get('/school-session-exports/{export}/download', [SchoolSessionController::class, 'downloadExport']);
    Route::delete('/school-sessions/{schoolSession}', [SchoolSessionController::class, 'destroy']);

    // Orders management
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::get('/orders/{order}/invoice', [AdminOrderController::class, 'downloadInvoice']);
    Route::get('/orders/{order}/download-link', [AdminOrderController::class, 'getDownloadLink']);
    Route::post('/orders/{order}/retry-payment', [AdminOrderController::class, 'retryPayment']);
    Route::put('/orders/{order}/ship', [AdminOrderController::class, 'markShipped']);
    Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
*/

Route::prefix('webhooks')->group(function () {
    Route::post('/sumup', [SumUpPaymentController::class, 'webhook']);
});
