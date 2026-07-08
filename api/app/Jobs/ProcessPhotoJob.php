<?php

namespace App\Jobs;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoUpload;
use App\Services\ImageProcessingService;
use App\Services\MinioStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessPhotoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public string $uploadId,
        public string $galleryId,
        public string $tempFilePath,
        public string $originalFilename,
        public string $mimeType
    ) {}

    public function handle(): void
    {
        $upload = PhotoUpload::find($this->uploadId);
        if (! $upload) {
            Log::error('ProcessPhotoJob: Upload not found', ['upload_id' => $this->uploadId]);

            return;
        }

        $gallery = Gallery::find($this->galleryId);
        if (! $gallery) {
            $upload->markAsFailed('Galerie non trouvée');
            $this->cleanupTempFile();

            return;
        }

        // Prod (NAS FS natif) : sync immédiate cross-container. Local (macOS virtio-fs) : marge.
        $waitSeconds = app()->environment('local') ? 30 : 3;
        $this->waitForTempFile(maxSeconds: $waitSeconds, pollIntervalSeconds: 1);

        try {
            $upload->markAsProcessing();

            $isVideo = str_starts_with($this->mimeType, 'video/');
            $photo = $isVideo ? $this->processVideo($gallery) : $this->processImage($gallery);

            if (! $photo) {
                // ImageProcessingService a retourné null (impossible de distinguer transient
                // de permanent) → on relance via le mécanisme de retry.
                throw new \RuntimeException("Échec du traitement de la photo {$this->originalFilename}");
            }

            $upload->markAsCompleted($photo->id);
            $this->cleanupTempFile();
        } catch (\Throwable $e) {
            // NE PAS cleanup le temp file : la queue va retry. NE PAS markAsFailed :
            // seul failed() le fait, après épuisement des retries.
            Log::warning('ProcessPhotoJob: attempt failed, will retry if attempts remain', [
                'upload_id' => $this->uploadId,
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function processImage(Gallery $gallery): ?Photo
    {
        $imageProcessingService = app(ImageProcessingService::class);

        $fullPath = Storage::disk('local')->path($this->tempFilePath);

        // `test=true` pour skip la validation interne (le fichier n'est pas un vrai upload HTTP).
        $tempFile = new UploadedFile(
            $fullPath,
            $this->originalFilename,
            $this->mimeType,
            null,
            true
        );

        $result = $imageProcessingService->processUploadedPhoto($tempFile, $gallery->id);

        if (! $result) {
            return null;
        }

        return $gallery->photos()->create([
            'file_path' => $result['hd_path'],
            'file_path_hd' => $result['hd_path'],
            'file_path_preview' => $result['preview_path'],
            'file_path_thumbnail' => $result['thumbnail_path'],
            'is_processed' => true,
            'title' => pathinfo($this->originalFilename, PATHINFO_FILENAME),
            'is_downloadable' => false,
            'metadata' => [
                'original_filename' => $this->originalFilename,
                'mime_type' => $this->mimeType,
                'storage_path' => $result['hd_path'],
            ],
        ]);
    }

    private function processVideo(Gallery $gallery): ?Photo
    {
        $storageService = app(MinioStorageService::class);

        $fullPath = Storage::disk('local')->path($this->tempFilePath);

        $tempFile = new UploadedFile(
            $fullPath,
            $this->originalFilename,
            $this->mimeType,
            null,
            true
        );

        $result = $storageService->uploadPhoto($tempFile, $gallery->id);

        if (! $result) {
            return null;
        }

        return $gallery->photos()->create([
            'file_path' => $result['path'],
            'title' => pathinfo($this->originalFilename, PATHINFO_FILENAME),
            'is_video' => true,
            'is_downloadable' => false,
            'is_processed' => true,
            'metadata' => [
                'original_filename' => $this->originalFilename,
                'mime_type' => $this->mimeType,
                'storage_path' => $result['path'],
            ],
        ]);
    }

    private function waitForTempFile(int $maxSeconds, int $pollIntervalSeconds): void
    {
        $disk = Storage::disk('local');
        $fullPath = $disk->path($this->tempFilePath);
        $deadline = time() + $maxSeconds;

        do {
            clearstatcache(true, $fullPath);
            if ($disk->exists($this->tempFilePath)) {
                return;
            }
            if (time() >= $deadline) {
                break;
            }
            sleep($pollIntervalSeconds);
        } while (true);

        throw new \RuntimeException(
            "Fichier temporaire non trouvé après {$maxSeconds}s d'attente: {$this->tempFilePath}"
        );
    }

    private function cleanupTempFile(): void
    {
        try {
            if (Storage::disk('local')->exists($this->tempFilePath)) {
                Storage::disk('local')->delete($this->tempFilePath);
            }
        } catch (\Exception $e) {
            Log::warning('ProcessPhotoJob: Failed to cleanup temp file', [
                'path' => $this->tempFilePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessPhotoJob: Job failed permanently', [
            'upload_id' => $this->uploadId,
            'error' => $exception->getMessage(),
        ]);

        try {
            $upload = PhotoUpload::find($this->uploadId);
            if ($upload) {
                $upload->markAsFailed('Échec après plusieurs tentatives: '.$exception->getMessage());
            }
        } catch (\Exception $e) {
            Log::error('ProcessPhotoJob: Failed to mark upload as failed', [
                'upload_id' => $this->uploadId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->cleanupTempFile();
    }
}
