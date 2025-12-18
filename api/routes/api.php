<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PrestationController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\FactureController;
use App\Http\Controllers\Api\Admin\NotificationController;
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

// Photos (public - watermarked)
Route::get('/photos/{photo}', [PhotoController::class, 'show']);
Route::post('/photos/{photo}/like', [PhotoController::class, 'like']);

// Contact
Route::post('/contact', [ContactController::class, 'send']);

// Gift cards (public)
Route::get('/gift-cards/validate/{code}', [GiftCardController::class, 'validate']);

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

    // Payments
    Route::post('/payments/stripe/create-intent', [PaymentController::class, 'createStripeIntent']);
    Route::post('/payments/stripe/confirm', [PaymentController::class, 'confirmStripePayment']);
    Route::post('/payments/paypal/create-order', [PaymentController::class, 'createPayPalOrder']);
    Route::post('/payments/paypal/capture', [PaymentController::class, 'capturePayPalOrder']);

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

    // Prestations management
    Route::apiResource('prestations', PrestationController::class)->except(['index', 'show']);
    Route::put('/prestations/{prestation}/toggle', [PrestationController::class, 'toggle']);

    // Reservations management
    Route::get('/reservations', [ReservationController::class, 'adminIndex']);
    Route::put('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);
    Route::get('/reservations/calendar', [ReservationController::class, 'calendar']);

    // Galleries management
    Route::apiResource('galleries', GalleryController::class)->except(['index', 'show']);
    Route::post('/galleries/{gallery}/photos', [PhotoController::class, 'store']);
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
    Route::put('/galleries/{gallery}/regenerate-token', [GalleryController::class, 'regenerateToken']);

    // Factures management
    Route::apiResource('factures', FactureController::class);
    Route::get('/factures/{facture}/pdf', [FactureController::class, 'downloadPdf']);
    Route::post('/factures/{facture}/send', [FactureController::class, 'send']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);

    // Gift cards
    Route::get('/gift-cards', [GiftCardController::class, 'index']);
    Route::put('/gift-cards/{giftCard}', [GiftCardController::class, 'update']);

    // Notifications
    Route::post('/notifications', [NotificationController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
*/

Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [PaymentController::class, 'handleStripeWebhook']);
    Route::post('/paypal', [PaymentController::class, 'handlePayPalWebhook']);
});
