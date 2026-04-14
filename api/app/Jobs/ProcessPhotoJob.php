<?php

namespace App\Jobs;

use App\Models\Gallery;
use App\Models\PhotoUpload;
use App\Services\ImageProcessingService;
use App\Services\MinioStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessPhotoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300; // 5 minutes for large images (7000x4600)

    public array $backoff = [5, 15, 30]; // Wait 5s, 15s, 30s between retries

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

        try {
            $upload->markAsProcessing();

            // Check if temp file exists
            if (! Storage::disk('local')->exists($this->tempFilePath)) {
                $upload->markAsFailed('Fichier temporaire non trouvé');

                return;
            }

            $isVideo = str_starts_with($this->mimeType, 'video/');

            if ($isVideo) {
                $photo = $this->processVideo($gallery);
            } else {
                $photo = $this->processImage($gallery);
            }

            if ($photo) {
                $upload->markAsCompleted($photo->id);
            } else {
                $upload->markAsFailed('Erreur lors du traitement de l\'image');
            }
        } catch (\Exception $e) {
            Log::error('ProcessPhotoJob: Processing failed', [
                'upload_id' => $this->uploadId,
                'error' => $e->getMessage(),
            ]);
            $upload->markAsFailed($e->getMessage());
        } finally {
            $this->cleanupTempFile();
        }
    }

    private function processImage(Gallery $gallery): ?\App\Models\Photo
    {
        $imageProcessingService = app(ImageProcessingService::class);

        // Get the full path to the temp file
        $fullPath = Storage::disk('local')->path($this->tempFilePath);

        // Create an UploadedFile instance from the temp file
        $tempFile = new \Illuminate\Http\UploadedFile(
            $fullPath,
            $this->originalFilename,
            $this->mimeType,
            null,
            true // Mark as test to skip validation
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

    private function processVideo(Gallery $gallery): ?\App\Models\Photo
    {
        $storageService = app(MinioStorageService::class);

        // Get the full path to the temp file
        $fullPath = Storage::disk('local')->path($this->tempFilePath);

        // Create an UploadedFile instance from the temp file
        $tempFile = new \Illuminate\Http\UploadedFile(
            $fullPath,
            $this->originalFilename,
            $this->mimeType,
            null,
            true // Mark as test to skip validation
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
