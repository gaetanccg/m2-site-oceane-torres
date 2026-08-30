<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingRequestController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EventGalleryController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImageProxyController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PrestationController;
use App\Http\Controllers\Api\SumUpPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
|
| Détail des trois niveaux d'exposition : docs/supervision.md §2.
| `/health/tables` a été supprimée : elle publiait le schéma de la base.
|
*/

Route::middleware('throttle:health')->group(function () {
    Route::get('/', [HealthController::class, 'live']);
    Route::get('/health', [HealthController::class, 'index']);
    Route::get('/health/live', [HealthController::class, 'live']);

    Route::middleware('health.token')->group(function () {
        Route::get('/health/details', [HealthController::class, 'details']);
        Route::get('/health/database', [HealthController::class, 'database']);
    });
});

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Authentication
Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// Prestations (public)
Route::get('/prestations', [PrestationController::class, 'index']);
Route::get('/prestations/{prestation}', [PrestationController::class, 'show']);

// Galleries (public)
Route::middleware('throttle:gallery-code')->get('/galleries/code/{code}', [GalleryController::class, 'showByShareCode']);
Route::get('/galleries/download/{token}', [GalleryController::class, 'showDownloadableByToken']);
Route::get('/galleries/{gallery}/download-zip', [GalleryController::class, 'downloadZip']);

// Photos (public - watermarked)
Route::post('/photos/{photo}/like', [PhotoController::class, 'like']);
Route::get('/photos/{photo}/download', [PhotoController::class, 'download']);

// Image proxy (secure image streaming with watermarks)
Route::middleware('throttle:images')->group(function () {
    Route::get('/images/preview/{photo}', [ImageProxyController::class, 'preview']);
    Route::get('/images/thumbnail/{photo}', [ImageProxyController::class, 'thumbnail']);
    Route::get('/images/clean/{photo}', [ImageProxyController::class, 'clean']);
    Route::get('/images/clean-thumb/{photo}', [ImageProxyController::class, 'cleanThumbnail']);
});
Route::middleware('throttle:downloads')->group(function () {
    Route::get('/images/download/{photo}', [ImageProxyController::class, 'download']);
});

// Contact
Route::middleware('throttle:public-form')->post('/contact', [ContactController::class, 'send']);

// Event Galleries (public)
Route::get('/events', [EventGalleryController::class, 'index']);
Route::get('/events/{gallery}', [EventGalleryController::class, 'show']);

// Gift cards (public)

// Booking request (public - sans authentification)
Route::middleware('throttle:public-form')->post('/booking-request', [BookingRequestController::class, 'store']);

// Cart (public - works with session for guests)
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::post('/add', [CartController::class, 'addItem']);
    Route::put('/item/{item}/type', [CartController::class, 'updateItemType']);
    Route::put('/item/{item}/quantity', [CartController::class, 'updateItemQuantity']);
    Route::delete('/item/{item}', [CartController::class, 'removeItem']);
    Route::delete('/clear', [CartController::class, 'clear']);
    Route::put('/email', [CartController::class, 'updateEmail']);

    Route::middleware('throttle:gift-code')->group(function () {
        Route::post('/gift-code', [CartController::class, 'applyGiftCode']);
    });
    Route::delete('/gift-code', [CartController::class, 'removeGiftCode']);
});

// Checkout & Orders (public for guest checkout)
Route::post('/checkout', [OrderController::class, 'createFromCart']);
Route::post('/checkout/confirm-free', [OrderController::class, 'confirmFreeOrder']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::get('/orders/{order}/download/{item}', [OrderController::class, 'downloadPhoto']);
Route::get('/orders/{order}/download-all', [OrderController::class, 'downloadAll']);
Route::get('/orders/{order}/invoice', [OrderController::class, 'downloadInvoice']);

// SumUp Payment (public)
Route::prefix('payments/sumup')->group(function () {
    Route::post('/verify', [SumUpPaymentController::class, 'verifyPayment']);
    Route::post('/cancel-checkout', [SumUpPaymentController::class, 'cancelCheckout']);

    // URL unique appelée par SumUp comme return_url :
    //   - GET  → redirection navigateur après 3DS (avec ?checkout_id=…)
    //   - POST → webhook CHECKOUT_STATUS_CHANGED
    // Rate-limit le webhook : SumUp envoie au max 1 webhook par checkout + retries
    // espacés (1m/5m/20m/2h). 60/min/IP est très large pour le légitime et défend
    // contre le spam par checkout id aléatoire (cf. webhook non signé).
    Route::get('/return', [SumUpPaymentController::class, 'browserReturn']);
    Route::middleware('throttle:sumup-webhook')
        ->post('/return', [SumUpPaymentController::class, 'webhook']);
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

    // Reservations

    // User galleries (private)

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

// Les 96 routes d'administration vivent dans `routes/admin.php`, montees avec leur
// prefixe et leur groupe de middleware dans `bootstrap/app.php`.

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
|
| Note : SumUp ne propose PAS de webhook configurable côté dashboard. Le
| webhook CHECKOUT_STATUS_CHANGED est envoyé en POST sur le `return_url`
| fourni à la création du checkout. La route correspondante vit donc dans
| le groupe `payments/sumup/` (voir POST /payments/sumup/return ci-dessus).
*/
