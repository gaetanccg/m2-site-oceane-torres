<?php

/*
|--------------------------------------------------------------------------
| Surface d'administration
|--------------------------------------------------------------------------
|
| Ce fichier ne declare AUCUNE protection : le prefixe `api/admin` et le groupe
| de middleware sont poses au montage, dans `bootstrap/app.php`. C'est
| deliberе — tant que le groupe vivait dans le corps de `api.php`, une accolade
| mal placee au milieu de 147 declarations aurait publie ces routes sans garde,
| et rien dans le fichier ne l'aurait signale.
|
| Consequence pratique : une route ajoutee ici est protegee par construction, et
| toute mesure d'isolation ulterieure (limite de debit, second facteur,
| restriction d'origine) se pose en une ligne au montage plutot qu'en autant
| d'editions qu'il y a de routes.
|
| `Tests\Feature\Admin\AdminSurfaceAccessTest` verifie les trois acteurs sur la
| surface entiere, et ne s'appuie PAS sur la presence du middleware pour savoir
| ce qui doit etre protege.
|
*/

use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EventCategoryController;
use App\Http\Controllers\Api\Admin\GiftCardController;
use App\Http\Controllers\Api\Admin\GiftCodeController;
use App\Http\Controllers\Api\Admin\LogController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PrivacyController;
use App\Http\Controllers\Api\Admin\ReservationController;
use App\Http\Controllers\Api\Admin\SchoolSessionController;
use App\Http\Controllers\Api\EventGalleryController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PrestationController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

Route::get('/health', [HealthController::class, 'details']);

// Clients management
Route::apiResource('clients', ClientController::class)->except(['destroy']);
Route::get('/clients/{client}/reservations', [ClientController::class, 'reservations']);
Route::post('/clients/{client}/gdpr-export', [ClientController::class, 'gdprExport']);

// RGPD / Données personnelles
Route::get('/privacy/search', [PrivacyController::class, 'search']);
Route::get('/privacy/audit', [PrivacyController::class, 'audit']);
Route::post('/privacy/export-all', [PrivacyController::class, 'exportAll']);
Route::post('/privacy/export-subject', [PrivacyController::class, 'exportSubject']);
Route::get('/privacy/exports/{export}', [PrivacyController::class, 'exportStatus']);
Route::get('/privacy/exports/{export}/download', [PrivacyController::class, 'downloadExport']);
Route::get('/privacy/erasure/preview', [PrivacyController::class, 'erasurePreview']);
Route::post('/privacy/erasure', [PrivacyController::class, 'erase']);

// Logs applicatifs
Route::get('/logs', [LogController::class, 'index']);
Route::get('/logs/download', [LogController::class, 'download']);
Route::delete('/logs', [LogController::class, 'clear']);

// Prestations management
Route::get('/prestations', [PrestationController::class, 'adminIndex']);
Route::apiResource('prestations', PrestationController::class)->except(['index', 'show']);
Route::put('/prestations/{prestation}/toggle', [PrestationController::class, 'toggle']);

// Reservations management
Route::get('/reservations', [ReservationController::class, 'index']);
Route::get('/reservations/calendar', [ReservationController::class, 'calendar']);
Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
Route::put('/reservations/{reservation}', [ReservationController::class, 'update']);
Route::put('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);
Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy']);

// Galleries management
Route::get('/galleries', [GalleryController::class, 'adminIndex']);
Route::get('/galleries/{gallery}', [GalleryController::class, 'adminShow']);
Route::apiResource('galleries', GalleryController::class)->except(['index', 'show']);
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
Route::post('/events/{gallery}/photos/async', [PhotoController::class, 'storeAsync']);

// Upload status (for polling)
Route::get('/upload-status', [PhotoController::class, 'uploadStatus']);

// Gift cards
Route::post('/gift-cards', [GiftCardController::class, 'store']);
Route::get('/gift-cards/stats', [GiftCardController::class, 'stats']);
Route::get('/gift-cards', [GiftCardController::class, 'index']);
Route::put('/gift-cards/{giftCard}', [GiftCardController::class, 'update']);

// Codes promo (Gift Codes) — distinct des Bons Cadeaux ci-dessus
Route::get('/gift-codes/stats', [GiftCodeController::class, 'stats']);
Route::get('/gift-codes/generate-code', [GiftCodeController::class, 'generateCode']);
Route::put('/gift-codes/{giftCode}/toggle', [GiftCodeController::class, 'toggle']);
Route::apiResource('gift-codes', GiftCodeController::class);

// Notifications

// School Sessions management
Route::get('/school-sessions', [SchoolSessionController::class, 'index']);
Route::post('/school-sessions', [SchoolSessionController::class, 'store']);
Route::get('/school-sessions/{schoolSession}', [SchoolSessionController::class, 'show']);
Route::put('/school-sessions/{schoolSession}', [SchoolSessionController::class, 'update']);
Route::get('/school-sessions/{schoolSession}/galleries', [SchoolSessionController::class, 'galleries']);
Route::get('/school-sessions/{schoolSession}/orders', [SchoolSessionController::class, 'orders']);
Route::put('/school-sessions/{schoolSession}/upload', [SchoolSessionController::class, 'upload']);
Route::post('/school-sessions/{schoolSession}/process', [SchoolSessionController::class, 'process']);
Route::post('/school-sessions/{schoolSession}/retry-failed-photos', [SchoolSessionController::class, 'retryFailedPhotos']);
Route::post('/school-sessions/{schoolSession}/send-messages', [SchoolSessionController::class, 'sendMessages']);
Route::get('/school-sessions/{schoolSession}/contacts', [SchoolSessionController::class, 'contacts']);
Route::post('/school-sessions/{schoolSession}/resend-messages', [SchoolSessionController::class, 'resendMessages']);
Route::post('/school-sessions/{schoolSession}/close', [SchoolSessionController::class, 'close']);
Route::post('/school-sessions/{schoolSession}/reopen', [SchoolSessionController::class, 'reopen']);
Route::post('/school-sessions/{schoolSession}/exports', [SchoolSessionController::class, 'createExport']);
Route::get('/school-sessions/{schoolSession}/exports/latest', [SchoolSessionController::class, 'latestExport']);
Route::get('/school-session-exports/{export}/download', [SchoolSessionController::class, 'downloadExport']);
Route::delete('/school-sessions/{schoolSession}', [SchoolSessionController::class, 'destroy']);

// Orders management
Route::get('/orders/stats', [AdminOrderController::class, 'stats']);
Route::get('/orders', [AdminOrderController::class, 'index']);
Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
Route::get('/orders/{order}/invoice', [AdminOrderController::class, 'downloadInvoice']);
Route::get('/orders/{order}/download-link', [AdminOrderController::class, 'getDownloadLink']);
Route::post('/orders/{order}/retry-payment', [AdminOrderController::class, 'retryPayment']);
Route::put('/orders/{order}/ship', [AdminOrderController::class, 'markShipped']);
Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy']);
