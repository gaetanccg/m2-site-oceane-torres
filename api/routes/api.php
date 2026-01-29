<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingRequestController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PrestationController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
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

// Contact
Route::post('/contact', [ContactController::class, 'send']);

// Event Galleries (public)
Route::get('/events', [GalleryController::class, 'eventIndex']);
Route::get('/events/{gallery}', [GalleryController::class, 'eventShow']);

// Gift cards (public)
Route::get('/gift-cards/validate/{code}', [GiftCardController::class, 'validate']);

// Booking request (public - sans authentification)
Route::post('/booking-request', [BookingRequestController::class, 'store']);

// Cart (public - works with session for guests)
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::post('/add', [CartController::class, 'addItem']);
    Route::put('/item/{item}/type', [CartController::class, 'updateItemType']);
    Route::delete('/item/{item}', [CartController::class, 'removeItem']);
    Route::delete('/clear', [CartController::class, 'clear']);
    Route::put('/email', [CartController::class, 'updateEmail']);
});

// Checkout & Orders (public for guest checkout)
Route::post('/checkout', [OrderController::class, 'createFromCart']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::get('/orders/{order}/download/{item}', [OrderController::class, 'downloadPhoto']);
Route::get('/orders/{order}/download-all', [OrderController::class, 'downloadAll']);
Route::post('/orders/by-email', [OrderController::class, 'getByEmail']);

// SumUp Payment (public)
Route::prefix('payments/sumup')->group(function () {
    Route::get('/config', [SumUpPaymentController::class, 'getConfig']);
    Route::post('/create-checkout', [SumUpPaymentController::class, 'createCheckout']);
    Route::get('/callback', [SumUpPaymentController::class, 'callback']);
    Route::post('/verify', [SumUpPaymentController::class, 'verifyPayment']);
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
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
    Route::put('/galleries/{gallery}/regenerate-token', [GalleryController::class, 'regenerateToken']);
    Route::post('/galleries/{gallery}/regenerate-code', [GalleryController::class, 'regenerateShareCode']);
    Route::post('/galleries/{gallery}/send-email', [GalleryController::class, 'sendAccessEmail']);

    // Photos management
    Route::put('/photos/{photo}/toggle-downloadable', [PhotoController::class, 'toggleDownloadable']);
    Route::put('/photos/bulk-downloadable', [PhotoController::class, 'bulkToggleDownloadable']);
    Route::put('/photos/sort-order', [PhotoController::class, 'updateSortOrder']);

    // Event Galleries management
    Route::get('/events', [GalleryController::class, 'adminEventIndex']);
    Route::get('/events/{gallery}', [GalleryController::class, 'adminEventShow']);
    Route::post('/events', [GalleryController::class, 'storeEvent']);
    Route::put('/events/{gallery}', [GalleryController::class, 'updateEvent']);
    Route::delete('/events/{gallery}', [GalleryController::class, 'destroyEvent']);
    Route::post('/events/{gallery}/photos', [PhotoController::class, 'store']);

    // Gift cards
    Route::get('/gift-cards', [GiftCardController::class, 'index']);
    Route::put('/gift-cards/{giftCard}', [GiftCardController::class, 'update']);

    // Notifications
    Route::post('/notifications', [NotificationController::class, 'store']);

    // Orders management
    Route::get('/orders', [OrderController::class, 'adminIndex']);
    Route::get('/orders/{order}', [OrderController::class, 'adminShow']);
    Route::delete('/orders/{order}', [OrderController::class, 'adminDestroy']);
});

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
*/

Route::prefix('webhooks')->group(function () {
    Route::post('/sumup', [SumUpPaymentController::class, 'webhook']);
});
