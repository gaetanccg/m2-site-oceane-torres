<?php

namespace App\Http\Controllers\Api;

use App\Helpers\MimeTypes;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Photo;
use App\Services\ImageProcessingService;
use App\Services\MinioStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImageProxyController extends Controller
{
    public function __construct(
        private MinioStorageService $storageService,
        private ImageProcessingService $imageProcessingService,
    ) {}

    /**
     * Stream preview version of a photo
     */
    public function preview(string $photo): Response
    {
        $model = $this->findPhotoCached($photo);
        if (! $model) {
            return $this->errorResponse('Photo non trouvée.', 404);
        }

        return $this->streamImage($model, 'preview');
    }

    /**
     * Stream thumbnail version of a photo
     */
    public function thumbnail(string $photo): Response
    {
        $model = $this->findPhotoCached($photo);
        if (! $model) {
            return $this->errorResponse('Photo non trouvée.', 404);
        }

        return $this->streamImage($model, 'thumbnail');
    }

    /**
     * Stream clean version (no watermark) for downloadable galleries
     */
    public function clean(Request $request, string $photo): Response
    {
        $model = $this->findPhotoCached($photo);
        if (! $model) {
            return $this->errorResponse('Photo non trouvée.', 404);
        }

        $token = $request->query('token');
        $gallery = $model->gallery;

        // Verify access via gallery token
        if (! $gallery || ! $gallery->isAccessible($token)) {
            return $this->errorResponse('Accès non autorisé.', 403);
        }

        // Only allow for downloadable photos
        if (! $model->is_downloadable) {
            return $this->errorResponse('Photo non téléchargeable.', 403);
        }

        return $this->streamCleanImage($model);
    }

    /**
     * Stream a small clean thumbnail (no watermark) for the downloadable gallery grid
     */
    public function cleanThumbnail(Request $request, string $photo): Response
    {
        $model = $this->findPhotoCached($photo);
        if (! $model) {
            return $this->errorResponse('Photo non trouvée.', 404);
        }

        $token = $request->query('token');
        $gallery = $model->gallery;

        // Verify access via gallery token
        if (! $gallery || ! $gallery->isAccessible($token)) {
            return $this->errorResponse('Accès non autorisé.', 403);
        }

        // Only allow for downloadable photos
        if (! $model->is_downloadable) {
            return $this->errorResponse('Photo non téléchargeable.', 403);
        }

        return $this->streamCleanThumbnail($model);
    }

    /**
     * Cached lookup with eager-loaded gallery — avoids hammering the DB pool
     * when many photos load in parallel from a gallery view.
     */
    private function findPhotoCached(string $photoId): ?Photo
    {
        return Cache::remember(
            "photo_meta_{$photoId}",
            3600,
            fn () => Photo::with('gallery')->find($photoId)
        );
    }

    /**
     * Stream clean image (resized but no watermark)
     */
    private function streamCleanImage(Photo $photo): Response
    {
        $originalPath = $photo->resolved_storage_path;

        // Generate clean preview on-the-fly with caching
        $cacheKey = "image_clean_{$photo->id}";

        $content = Cache::remember($cacheKey, 3600, function () use ($originalPath) {
            return $this->imageProcessingService->generateCleanPreviewOnTheFly($originalPath);
        });

        if (! $content) {
            return $this->errorResponse('Image non disponible.', 404);
        }

        $extension = pathinfo($photo->file_path, PATHINFO_EXTENSION) ?: 'jpg';

        return $this->createImageResponse($content, "image.{$extension}");
    }

    /**
     * Stream clean thumbnail (small, no watermark) for grid display
     */
    private function streamCleanThumbnail(Photo $photo): Response
    {
        $originalPath = $photo->resolved_storage_path;

        // Generate clean thumbnail on-the-fly with caching
        $cacheKey = "image_clean_thumb_{$photo->id}";

        $content = Cache::remember($cacheKey, 3600, function () use ($originalPath) {
            return $this->imageProcessingService->generateCleanThumbnailOnTheFly($originalPath);
        });

        if (! $content) {
            return $this->errorResponse('Image non disponible.', 404);
        }

        $extension = pathinfo($photo->file_path, PATHINFO_EXTENSION) ?: 'jpg';

        return $this->createImageResponse($content, "image.{$extension}");
    }

    /**
     * Download HD version after purchase validation
     */
    public function download(Request $request, Photo $photo): Response
    {
        $token = $request->query('token');
        $orderId = $request->query('order');

        // Validate access
        if (! $token || ! $orderId) {
            return $this->errorResponse('Token et commande requis.', 403);
        }

        try {
            $order = Order::findOrFail($orderId);

            // Verify order is paid
            if ($order->status !== 'paid') {
                return $this->errorResponse('Commande non payée.', 403);
            }

            // Verify token is valid
            if (! $order->isDownloadTokenValid($token)) {
                return $this->errorResponse('Token invalide ou expire.', 403);
            }

            // Verify photo is in order
            $orderItem = $order->items()->where('photo_id', $photo->id)->first();
            if (! $orderItem) {
                return $this->errorResponse('Photo non incluse dans cette commande.', 403);
            }

            // Get HD path
            $hdPath = $photo->resolved_storage_path;

            // Stream HD version
            $content = $this->storageService->getFileContent($hdPath);
            if (! $content) {
                return $this->errorResponse('Fichier non trouve.', 404);
            }

            // Mark as downloaded
            $orderItem->markAsDownloaded();

            // Get filename for download
            $extension = pathinfo($hdPath, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = ($photo->title ?? 'photo').'.'.$extension;
            $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);

            return $this->createDownloadResponse($content, $filename, MimeTypes::fromExtension($extension));
        } catch (\Exception $e) {
            Log::error('Download failed', [
                'photo_id' => $photo->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erreur lors du téléchargement.', 500);
        }
    }

    /**
     * Stream image with fallback for unprocessed photos
     */
    private function streamImage(Photo $photo, string $version): Response
    {
        // Check if photo has processed version
        $pathField = "file_path_{$version}";
        $processedPath = $photo->$pathField;

        if ($processedPath && $photo->is_processed) {
            // Processed version exists - stream it
            $content = $this->storageService->getFileContent($processedPath);
            if ($content) {
                return $this->createImageResponse($content, $processedPath);
            }
        }

        // Fallback: generate on-the-fly with caching
        $cacheKey = "image_{$version}_{$photo->id}";

        $content = Cache::remember($cacheKey, 3600, function () use ($photo, $version) {
            $originalPath = $photo->resolved_storage_path;

            if ($version === 'preview') {
                return $this->imageProcessingService->generatePreviewOnTheFly($originalPath);
            }

            return $this->imageProcessingService->generateThumbnailOnTheFly($originalPath);
        });

        if (! $content) {
            return $this->errorResponse('Image non disponible.', 404);
        }

        $extension = pathinfo($photo->file_path, PATHINFO_EXTENSION) ?: 'jpg';

        return $this->createImageResponse($content, "image.{$extension}");
    }

    /**
     * Create response for image streaming
     */
    private function createImageResponse(string $content, string $path): Response
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
        $mimeType = MimeTypes::fromExtension($extension);

        return response($content, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($content),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'Pragma' => 'cache',
        ]);
    }

    /**
     * Create response for file download
     */
    private function createDownloadResponse(string $content, string $filename, string $mimeType): Response
    {
        return response($content, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($content),
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    /**
     * Create error response
     */
    private function errorResponse(string $message, int $status): Response
    {
        return response($message, $status, [
            'Content-Type' => 'text/plain',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
