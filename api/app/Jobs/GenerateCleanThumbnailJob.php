<?php

namespace App\Jobs;

use App\Helpers\MimeTypes;
use App\Models\Photo;
use App\Services\ImageProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generate and persist the clean (no-watermark) thumbnail for a photo that has
 * been marked downloadable, so the download gallery grid can be served from
 * direct MinIO signed URLs (fast) instead of on-the-fly PHP-FPM generation (slow).
 */
class GenerateCleanThumbnailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [10, 30, 60];

    public function __construct(public string $photoId) {}

    public function handle(ImageProcessingService $imageProcessingService): void
    {
        $photo = Photo::find($this->photoId);

        // Skip if gone, no longer downloadable, a video, or already generated.
        if (! $photo || $photo->is_video || ! $photo->is_downloadable) {
            return;
        }
        if ($photo->file_path_thumbnail_clean) {
            return;
        }

        $targetPath = $photo->cleanThumbnailStoragePath();
        $mimeType = MimeTypes::fromExtension(pathinfo($targetPath, PATHINFO_EXTENSION) ?: 'jpg');

        $ok = $imageProcessingService->generateAndStoreCleanThumbnail(
            $photo->resolved_storage_path,
            $targetPath,
            $mimeType,
        );

        if (! $ok) {
            // Let the queue retry (transient MinIO/Imagick failures are common).
            throw new \RuntimeException("Clean thumbnail generation failed for photo {$photo->id}");
        }

        $photo->update(['file_path_thumbnail_clean' => $targetPath]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateCleanThumbnailJob failed permanently', [
            'photo_id' => $this->photoId,
            'error' => $exception->getMessage(),
        ]);
    }
}
