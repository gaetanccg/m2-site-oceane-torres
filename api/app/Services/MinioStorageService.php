<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MinioStorageService
{
    private string $bucket;

    private string $disk = 'minio';

    public function __construct()
    {
        $this->bucket = config('filesystems.disks.minio.bucket');
    }

    public function uploadPhoto(UploadedFile $file, string $galleryId): ?array
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid().'.'.$extension;
        $path = "{$galleryId}/{$filename}";

        try {
            Storage::disk($this->disk)->put($path, file_get_contents($file->getRealPath()), [
                'ContentType' => $file->getMimeType(),
            ]);

            return [
                'path' => $path,
                'url' => $this->getSignedUrl($path),
                'filename' => $filename,
            ];
        } catch (\Exception $e) {
            \Log::error('MinIO upload failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function deletePhoto(string $path): bool
    {
        try {
            return Storage::disk($this->disk)->delete($path);
        } catch (\Exception $e) {
            \Log::error('MinIO delete failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function deleteGalleryFolder(string $galleryId): bool
    {
        try {
            // Use allFiles to get files recursively (includes subfolders like original/, preview/, thumbnail/)
            $files = Storage::disk($this->disk)->allFiles($galleryId);

            if (empty($files)) {
                \Log::info('MinIO gallery folder empty or not found', ['gallery_id' => $galleryId]);

                return true;
            }

            \Log::info('MinIO deleting gallery files', [
                'gallery_id' => $galleryId,
                'files_count' => count($files),
            ]);

            // Delete all files
            $result = Storage::disk($this->disk)->delete($files);

            // Also try to delete the empty directories
            // Note: S3/MinIO doesn't have real directories, but this cleans up any markers
            foreach (['original', 'preview', 'thumbnail'] as $subdir) {
                try {
                    Storage::disk($this->disk)->deleteDirectory("{$galleryId}/{$subdir}");
                } catch (\Exception $e) {
                    // Ignore errors for subdirectory cleanup
                }
            }

            // Delete the main gallery directory
            try {
                Storage::disk($this->disk)->deleteDirectory($galleryId);
            } catch (\Exception $e) {
                // Ignore errors for main directory cleanup
            }

            return $result;
        } catch (\Exception $e) {
            \Log::error('MinIO folder delete failed', [
                'gallery_id' => $galleryId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function listFiles(string $galleryId): array
    {
        try {
            return Storage::disk($this->disk)->files($galleryId);
        } catch (\Exception $e) {
            \Log::error('MinIO list failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function getSignedUrl(string $path, int $expiresIn = 3600): ?string
    {
        try {
            return Storage::disk($this->disk)->temporaryUrl($path, now()->addSeconds($expiresIn));
        } catch (\Exception $e) {
            \Log::error('MinIO signed URL failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Download a photo and return the temporary file path
     */
    public function downloadPhoto(string $path): ?string
    {
        try {
            $content = Storage::disk($this->disk)->get($path);
            if (! $content) {
                return null;
            }

            // Create a temp file and write content
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $tempFile = tempnam(sys_get_temp_dir(), 'photo_').'.'.$extension;
            file_put_contents($tempFile, $content);

            return $tempFile;
        } catch (\Exception $e) {
            \Log::error('MinIO download failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get raw file content
     */
    public function getFileContent(string $path): ?string
    {
        try {
            return Storage::disk($this->disk)->get($path);
        } catch (\Exception $e) {
            \Log::error('MinIO get content failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
