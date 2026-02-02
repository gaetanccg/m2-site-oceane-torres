<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class ImageProcessingService
{
    private ImageManager $manager;

    private MinioStorageService $storageService;

    private string $disk = 'minio';

    // Image dimensions
    private const PREVIEW_MAX_WIDTH = 1200;

    private const THUMBNAIL_MAX_WIDTH = 400;

    // Quality settings (kept high for better gallery display)
    private const HD_QUALITY = 100;

    private const PREVIEW_QUALITY = 95;

    private const THUMBNAIL_QUALITY = 90;

    // Watermark settings
    private const WATERMARK_TEXT = '@ Oceane Torres';

    private const PREVIEW_WATERMARK_OPACITY = 0.3;

    private const THUMBNAIL_WATERMARK_OPACITY = 0.5;

    public function __construct()
    {
        $this->manager = new ImageManager(new GdDriver);
        $this->storageService = new MinioStorageService;
    }

    /**
     * Process an uploaded photo and create all versions (HD, preview, thumbnail)
     */
    public function processUploadedPhoto(UploadedFile $file, string $galleryId): ?array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $uuid = (string) Str::uuid();
        $filename = "{$uuid}.{$extension}";

        try {
            // Read original image
            $image = $this->manager->read($file->getRealPath());

            // Define paths
            $hdPath = "{$galleryId}/hd/{$filename}";
            $previewPath = "{$galleryId}/preview/{$filename}";
            $thumbnailPath = "{$galleryId}/thumbnail/{$filename}";

            // 1. Upload HD (original, high quality)
            $hdContent = $this->encodeImage($image, $extension, self::HD_QUALITY);
            $this->uploadContent($hdPath, $hdContent, $file->getMimeType());

            // 2. Create and upload preview (1200px + watermark)
            $previewImage = $this->createPreviewVersion($image, $extension);
            $previewContent = $this->encodeImage($previewImage, $extension, self::PREVIEW_QUALITY);
            $this->uploadContent($previewPath, $previewContent, $file->getMimeType());

            // 3. Create and upload thumbnail (400px + strong watermark)
            $thumbnailImage = $this->createThumbnailVersion($image, $extension);
            $thumbnailContent = $this->encodeImage($thumbnailImage, $extension, self::THUMBNAIL_QUALITY);
            $this->uploadContent($thumbnailPath, $thumbnailContent, $file->getMimeType());

            return [
                'hd_path' => $hdPath,
                'preview_path' => $previewPath,
                'thumbnail_path' => $thumbnailPath,
                'filename' => $filename,
            ];
        } catch (\Exception $e) {
            Log::error('Image processing failed', [
                'error' => $e->getMessage(),
                'gallery_id' => $galleryId,
            ]);

            return null;
        }
    }

    /**
     * Process an existing photo from storage
     */
    public function processExistingPhoto(string $originalPath, string $galleryId): ?array
    {
        try {
            // Get the original file content
            $content = $this->storageService->getFileContent($originalPath);
            if (! $content) {
                Log::error('Could not retrieve original file', ['path' => $originalPath]);

                return null;
            }

            // Get extension from path
            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $uuid = (string) Str::uuid();
            $filename = "{$uuid}.{$extension}";

            // Read image from content
            $image = $this->manager->read($content);

            // Get mime type
            $mimeType = $this->getMimeTypeFromExtension($extension);

            // Define paths
            $hdPath = "{$galleryId}/hd/{$filename}";
            $previewPath = "{$galleryId}/preview/{$filename}";
            $thumbnailPath = "{$galleryId}/thumbnail/{$filename}";

            // 1. Upload HD (original, high quality)
            $hdContent = $this->encodeImage($image, $extension, self::HD_QUALITY);
            $this->uploadContent($hdPath, $hdContent, $mimeType);

            // 2. Create and upload preview
            $previewImage = $this->createPreviewVersion($image, $extension);
            $previewContent = $this->encodeImage($previewImage, $extension, self::PREVIEW_QUALITY);
            $this->uploadContent($previewPath, $previewContent, $mimeType);

            // 3. Create and upload thumbnail
            $thumbnailImage = $this->createThumbnailVersion($image, $extension);
            $thumbnailContent = $this->encodeImage($thumbnailImage, $extension, self::THUMBNAIL_QUALITY);
            $this->uploadContent($thumbnailPath, $thumbnailContent, $mimeType);

            return [
                'hd_path' => $hdPath,
                'preview_path' => $previewPath,
                'thumbnail_path' => $thumbnailPath,
                'filename' => $filename,
            ];
        } catch (\Exception $e) {
            Log::error('Existing photo processing failed', [
                'error' => $e->getMessage(),
                'original_path' => $originalPath,
            ]);

            return null;
        }
    }

    /**
     * Generate preview version on-the-fly (for fallback)
     */
    public function generatePreviewOnTheFly(string $originalPath): ?string
    {
        try {
            $content = $this->storageService->getFileContent($originalPath);
            if (! $content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $image = $this->manager->read($content);
            $previewImage = $this->createPreviewVersion($image, $extension);

            return $this->encodeImage($previewImage, $extension, self::PREVIEW_QUALITY);
        } catch (\Exception $e) {
            Log::error('On-the-fly preview generation failed', [
                'error' => $e->getMessage(),
                'path' => $originalPath,
            ]);

            return null;
        }
    }

    /**
     * Generate thumbnail version on-the-fly (for fallback)
     */
    public function generateThumbnailOnTheFly(string $originalPath): ?string
    {
        try {
            $content = $this->storageService->getFileContent($originalPath);
            if (! $content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $image = $this->manager->read($content);
            $thumbnailImage = $this->createThumbnailVersion($image, $extension);

            return $this->encodeImage($thumbnailImage, $extension, self::THUMBNAIL_QUALITY);
        } catch (\Exception $e) {
            Log::error('On-the-fly thumbnail generation failed', [
                'error' => $e->getMessage(),
                'path' => $originalPath,
            ]);

            return null;
        }
    }

    /**
     * Create preview version (1200px max + watermark)
     */
    private function createPreviewVersion(ImageInterface $image, string $extension): ImageInterface
    {
        // Clone the image to avoid modifying the original
        $preview = clone $image;

        // Resize if larger than max width
        $width = $preview->width();
        if ($width > self::PREVIEW_MAX_WIDTH) {
            $preview->scaleDown(width: self::PREVIEW_MAX_WIDTH);
        }

        // Apply watermark
        $this->applyWatermark($preview, self::PREVIEW_WATERMARK_OPACITY);

        return $preview;
    }

    /**
     * Create thumbnail version (400px max + strong watermark)
     */
    private function createThumbnailVersion(ImageInterface $image, string $extension): ImageInterface
    {
        // Clone the image to avoid modifying the original
        $thumbnail = clone $image;

        // Resize if larger than max width
        $width = $thumbnail->width();
        if ($width > self::THUMBNAIL_MAX_WIDTH) {
            $thumbnail->scaleDown(width: self::THUMBNAIL_MAX_WIDTH);
        }

        // Apply strong watermark
        $this->applyWatermark($thumbnail, self::THUMBNAIL_WATERMARK_OPACITY);

        return $thumbnail;
    }

    /**
     * Apply diagonal watermark pattern to image
     */
    private function applyWatermark(ImageInterface $image, float $opacity): void
    {
        $width = $image->width();
        $height = $image->height();

        $fontSize = max(48, min($width, $height) / 5);

        // Create watermark pattern
        $watermarkText = self::WATERMARK_TEXT;

        // Calculate alpha value (0-127 where 0 is opaque, 127 is transparent)
        $alpha = (int) ((1 - $opacity) * 127);
        $color = "rgba(255, 255, 255, {$opacity})";

        $stepX = $width / 3;
        $stepY = $height / 3;

        // Apply watermarks in a diagonal grid pattern
        for ($y = -$height; $y < $height * 2; $y += $stepY) {
            for ($x = -$width; $x < $width * 2; $x += $stepX) {
                // Offset alternating rows
                $offsetX = (($y / $stepY) % 2 == 0) ? 0 : $stepX / 2;

                $posX = (int) ($x + $offsetX);
                $posY = (int) $y;

                if ($posX > -300 && $posX < $width + 300 && $posY > -150 && $posY < $height + 150) {
                    $image->text($watermarkText, $posX, $posY, function ($font) use ($fontSize, $color) {
                        $font->size($fontSize);
                        $font->color($color);
                        $font->angle(-25);
                        $font->align('center');
                        $font->valign('middle');
                    });
                }
            }
        }
    }

    /**
     * Encode image to string based on extension
     */
    private function encodeImage(ImageInterface $image, string $extension, int $quality): string
    {
        return match ($extension) {
            'png' => $image->toPng()->toString(),
            'gif' => $image->toGif()->toString(),
            'webp' => $image->toWebp($quality)->toString(),
            default => $image->toJpeg($quality)->toString(),
        };
    }

    /**
     * Upload content to MinIO
     */
    private function uploadContent(string $path, string $content, string $mimeType): void
    {
        Storage::disk($this->disk)->put($path, $content, [
            'ContentType' => $mimeType,
        ]);
    }

    /**
     * Get MIME type from extension
     */
    private function getMimeTypeFromExtension(string $extension): string
    {
        return match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Delete all versions of a photo
     */
    public function deletePhotoVersions(string $galleryId, string $hdPath, ?string $previewPath, ?string $thumbnailPath): void
    {
        $paths = array_filter([$hdPath, $previewPath, $thumbnailPath]);

        foreach ($paths as $path) {
            try {
                Storage::disk($this->disk)->delete($path);
            } catch (\Exception $e) {
                Log::error('Failed to delete photo version', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
