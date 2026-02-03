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
    private const PREVIEW_MAX_WIDTH = 2560; // QHD for high-quality gallery viewing

    private const THUMBNAIL_MAX_WIDTH = 600; // Larger thumbnails for better grid display

    // Quality settings (kept high for better gallery display)
    private const PREVIEW_QUALITY = 95;

    private const THUMBNAIL_QUALITY = 90;

    // Watermark settings
    private const WATERMARK_TEXT = '© Oceane Torres';

    private const PREVIEW_WATERMARK_OPACITY = 0.4;

    private const THUMBNAIL_WATERMARK_OPACITY = 0.6;

    // Font path for watermark (TTF required for custom sizes)
    private const WATERMARK_FONT = 'fonts/Amsterdam.ttf';

    public function __construct()
    {
        $this->manager = new ImageManager(new GdDriver);
        $this->storageService = new MinioStorageService;
    }

    /**
     * Process an uploaded photo and create all versions (original, preview, thumbnail)
     */
    public function processUploadedPhoto(UploadedFile $file, string $galleryId): ?array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $uuid = (string) Str::uuid();
        $filename = "{$uuid}.{$extension}";

        try {
            // Define paths
            $originalPath = "{$galleryId}/original/{$filename}";
            $previewPath = "{$galleryId}/preview/{$filename}";
            $thumbnailPath = "{$galleryId}/thumbnail/{$filename}";

            // 1. Upload original file as-is (no re-encoding, preserves 100% quality)
            $originalContent = file_get_contents($file->getRealPath());
            $this->uploadContent($originalPath, $originalContent, $file->getMimeType());

            // Read image for processing versions
            $image = $this->manager->read($file->getRealPath());

            // 2. Create and upload preview (2560px + watermark)
            $previewImage = $this->createPreviewVersion($image, $extension);
            $previewContent = $this->encodeImage($previewImage, $extension, self::PREVIEW_QUALITY);
            $this->uploadContent($previewPath, $previewContent, $file->getMimeType());

            // 3. Create and upload thumbnail (600px + strong watermark)
            $thumbnailImage = $this->createThumbnailVersion($image, $extension);
            $thumbnailContent = $this->encodeImage($thumbnailImage, $extension, self::THUMBNAIL_QUALITY);
            $this->uploadContent($thumbnailPath, $thumbnailContent, $file->getMimeType());

            return [
                'hd_path' => $originalPath, // Keep key name for compatibility
                'original_path' => $originalPath,
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

            // Get mime type
            $mimeType = $this->getMimeTypeFromExtension($extension);

            // Define paths
            $newOriginalPath = "{$galleryId}/original/{$filename}";
            $previewPath = "{$galleryId}/preview/{$filename}";
            $thumbnailPath = "{$galleryId}/thumbnail/{$filename}";

            // 1. Upload original as-is (no re-encoding)
            $this->uploadContent($newOriginalPath, $content, $mimeType);

            // Read image from content for processing
            $image = $this->manager->read($content);

            // 2. Create and upload preview
            $previewImage = $this->createPreviewVersion($image, $extension);
            $previewContent = $this->encodeImage($previewImage, $extension, self::PREVIEW_QUALITY);
            $this->uploadContent($previewPath, $previewContent, $mimeType);

            // 3. Create and upload thumbnail
            $thumbnailImage = $this->createThumbnailVersion($image, $extension);
            $thumbnailContent = $this->encodeImage($thumbnailImage, $extension, self::THUMBNAIL_QUALITY);
            $this->uploadContent($thumbnailPath, $thumbnailContent, $mimeType);

            return [
                'hd_path' => $newOriginalPath,
                'original_path' => $newOriginalPath,
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
     * Generate clean preview version on-the-fly (no watermark, for downloadable galleries)
     */
    public function generateCleanPreviewOnTheFly(string $originalPath): ?string
    {
        try {
            $content = $this->storageService->getFileContent($originalPath);
            if (! $content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $image = $this->manager->read($content);

            // Resize without watermark
            $width = $image->width();
            if ($width > self::PREVIEW_MAX_WIDTH) {
                $image->scaleDown(width: self::PREVIEW_MAX_WIDTH);
            }

            return $this->encodeImage($image, $extension, self::PREVIEW_QUALITY);
        } catch (\Exception $e) {
            Log::error('On-the-fly clean preview generation failed', [
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
     * Apply horizontal watermark pattern to image
     */
    private function applyWatermark(ImageInterface $image, float $opacity): void
    {
        $width = $image->width();
        $height = $image->height();

        // Font size: 5% of the smaller dimension (half of previous)
        $fontSize = (int) (min($width, $height) * 0.05);
        $fontSize = max($fontSize, 12);

        $watermarkText = self::WATERMARK_TEXT;
        $color = "rgba(255, 255, 255, $opacity)";

        $fontPath = $this->getFontPath();

        // Estimate text dimensions (approximate: width ≈ fontSize * 0.6 * chars, height ≈ fontSize)
        $textWidth = $fontSize * 0.6 * strlen($watermarkText);
        $textHeight = $fontSize;

        // Calculate grid spacing to avoid overlap
        // Add padding (1.5x text size) between watermarks
        $minStepX = $textWidth * 1.5;
        $minStepY = $textHeight * 2.5;

        // Calculate number of columns and rows that fit without overlap
        $cols = max(1, (int) floor($width / $minStepX));
        $rows = max(1, (int) floor($height / $minStepY));

        // Limit to reasonable numbers
        $cols = min($cols, 3);
        $rows = min($rows, 4);

        $stepX = $width / $cols;
        $stepY = $height / $rows;

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $posX = (int) ($stepX * $col + $stepX / 2);
                $posY = (int) ($stepY * $row + $stepY / 2);

                $image->text($watermarkText, $posX, $posY, function ($font) use ($fontSize, $color, $fontPath) {
                    if ($fontPath) {
                        $font->filename($fontPath);
                    }
                    $font->size($fontSize);
                    $font->color($color);
                    $font->angle(0);
                    $font->align('center');
                    $font->valign('middle');
                });
            }
        }
    }

    /**
     * Get the path to the watermark font file
     */
    private function getFontPath(): ?string
    {
        // Try custom font in storage
        $customFont = storage_path('app/'.self::WATERMARK_FONT);
        if (file_exists($customFont)) {
            Log::info('Watermark font found', ['path' => $customFont]);

            return $customFont;
        }

        // Try system fonts (Alpine/Linux/Ubuntu/Docker)
        $systemFonts = [
            // Alpine Linux (ttf-dejavu package)
            '/usr/share/fonts/ttf-dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/ttf-dejavu/DejaVuSans-Bold.ttf',
            // Debian/Ubuntu
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            // Other Linux
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ];

        foreach ($systemFonts as $font) {
            if (file_exists($font)) {
                Log::info('Watermark system font found', ['path' => $font]);

                return $font;
            }
        }

        // macOS system fonts
        $macFonts = [
            '/Library/Fonts/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
        ];

        foreach ($macFonts as $font) {
            if (file_exists($font)) {
                Log::info('Watermark macOS font found', ['path' => $font]);

                return $font;
            }
        }

        Log::warning('No watermark font found! Watermarks will be tiny.');

        return null;
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
