<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Modifiers\AlignRotationModifier;

class ImageProcessingService
{
    private ImageManager $rawManager;

    private MinioStorageService $storageService;

    private string $disk = 'minio';

    // Image dimensions
    private const PREVIEW_MAX_WIDTH = 2560; // QHD for high-quality gallery viewing

    private const THUMBNAIL_MAX_WIDTH = 600; // Larger thumbnails for better grid display

    // Quality settings (kept high for better gallery display)
    private const PREVIEW_QUALITY = 95;

    private const THUMBNAIL_QUALITY = 90;

    // Watermark settings
    private const WATERMARK_TEXT = '©Oceane Torres';

    // Central big text opacities
    private const PREVIEW_CENTRAL_OPACITY = 0.5;

    private const THUMBNAIL_CENTRAL_OPACITY = 0.5;

    // Diagonal grid text opacities
    private const PREVIEW_GRID_OPACITY = 0.8;

    private const THUMBNAIL_GRID_OPACITY = 0.7;

    // Font path for watermark (TTF required for custom sizes)
    private const WATERMARK_FONT = 'fonts/Amsterdam.ttf';

    public function __construct()
    {
        $this->rawManager = new ImageManager(new GdDriver, autoOrientation: false);
        $this->storageService = new MinioStorageService;
    }

    /**
     * Process an uploaded photo and create all versions (original, preview, thumbnail)
     */
    public function processUploadedPhoto(UploadedFile $file, string $galleryId): ?array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $uuid = (string)Str::uuid();
        $filename = "{$uuid}.{$extension}";

        try {
            $originalPath = "{$galleryId}/original/{$filename}";
            $previewPath = "{$galleryId}/preview/{$filename}";
            $thumbnailPath = "{$galleryId}/thumbnail/{$filename}";

            // 1. Upload original file as-is (no re-encoding, preserves 100% quality)
            $originalContent = file_get_contents($file->getRealPath());
            $this->uploadContent($originalPath, $originalContent, $file->getMimeType());
            unset($originalContent);

            // Get oriented dimensions without loading full image into GD
            [$originalWidth, $originalHeight] = $this->getOrientedDimensions($file->getRealPath());

            // 2. Create and upload preview (2560px + watermark)
            $preview = $this->readScaledOriented($file->getRealPath(), self::PREVIEW_MAX_WIDTH);
            $this->applyWatermark($preview, self::PREVIEW_CENTRAL_OPACITY, self::PREVIEW_GRID_OPACITY);
            $previewContent = $this->encodeImage($preview, $extension, self::PREVIEW_QUALITY);
            $this->uploadContent($previewPath, $previewContent, $file->getMimeType());
            unset($preview, $previewContent);

            // 3. Create and upload thumbnail (600px + strong watermark)
            $thumbnail = $this->readScaledOriented($file->getRealPath(), self::THUMBNAIL_MAX_WIDTH);
            $this->applyWatermark($thumbnail, self::THUMBNAIL_CENTRAL_OPACITY, self::THUMBNAIL_GRID_OPACITY);
            $thumbnailContent = $this->encodeImage($thumbnail, $extension, self::THUMBNAIL_QUALITY);
            $this->uploadContent($thumbnailPath, $thumbnailContent, $file->getMimeType());
            unset($thumbnail, $thumbnailContent);

            return [
                'hd_path' => $originalPath,
                'original_path' => $originalPath,
                'preview_path' => $previewPath,
                'thumbnail_path' => $thumbnailPath,
                'filename' => $filename,
                'width' => $originalWidth,
                'height' => $originalHeight,
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
            if (!$content) {
                Log::error('Could not retrieve original file', ['path' => $originalPath]);

                return null;
            }

            // Get extension from path
            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ? : 'jpg';
            $uuid = (string)Str::uuid();
            $filename = "{$uuid}.{$extension}";

            // Get mime type
            $mimeType = $this->getMimeTypeFromExtension($extension);

            // Define paths
            $newOriginalPath = "{$galleryId}/original/{$filename}";
            $previewPath = "{$galleryId}/preview/{$filename}";
            $thumbnailPath = "{$galleryId}/thumbnail/{$filename}";

            // 1. Upload original as-is (no re-encoding)
            $this->uploadContent($newOriginalPath, $content, $mimeType);

            // 2. Create and upload preview
            $preview = $this->readScaledOriented($content, self::PREVIEW_MAX_WIDTH);
            $this->applyWatermark($preview, self::PREVIEW_CENTRAL_OPACITY, self::PREVIEW_GRID_OPACITY);
            $previewContent = $this->encodeImage($preview, $extension, self::PREVIEW_QUALITY);
            $this->uploadContent($previewPath, $previewContent, $mimeType);
            unset($preview, $previewContent);

            // 3. Create and upload thumbnail
            $thumbnail = $this->readScaledOriented($content, self::THUMBNAIL_MAX_WIDTH);
            $this->applyWatermark($thumbnail, self::THUMBNAIL_CENTRAL_OPACITY, self::THUMBNAIL_GRID_OPACITY);
            $thumbnailContent = $this->encodeImage($thumbnail, $extension, self::THUMBNAIL_QUALITY);
            $this->uploadContent($thumbnailPath, $thumbnailContent, $mimeType);
            unset($thumbnail, $thumbnailContent);

            // Free source content
            unset($content);

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
            if (!$content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ? : 'jpg';
            $image = $this->readScaledOriented($content, self::PREVIEW_MAX_WIDTH);
            $this->applyWatermark($image, self::PREVIEW_CENTRAL_OPACITY, self::PREVIEW_GRID_OPACITY);

            return $this->encodeImage($image, $extension, self::PREVIEW_QUALITY);
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
            if (!$content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ? : 'jpg';
            $image = $this->readScaledOriented($content, self::THUMBNAIL_MAX_WIDTH);
            $this->applyWatermark($image, self::THUMBNAIL_CENTRAL_OPACITY, self::THUMBNAIL_GRID_OPACITY);

            return $this->encodeImage($image, $extension, self::THUMBNAIL_QUALITY);
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
            if (!$content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ? : 'jpg';
            $image = $this->readScaledOriented($content, self::PREVIEW_MAX_WIDTH);

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
     * Read image, scale down, then apply EXIF orientation. Memory-safe for large portraits.
     *
     * GD auto-orientation rotates BEFORE scaling, which requires 3x full-size buffers
     * and causes OOM on large portrait images. This method scales FIRST (small buffer),
     * then rotates (safe on the already-small image).
     */
    private function readScaledOriented(mixed $source, int $maxWidth): ImageInterface
    {
        $image = $this->rawManager->read($source);

        // Check if EXIF orientation will swap width/height (90° or 270° rotation)
        $orientation = $image->exif('IFD0.Orientation');
        $willTranspose = in_array($orientation, [5, 6, 7, 8]);

        if ($willTranspose) {
            // After rotation, raw height becomes width → scale raw height to target
            if ($image->height() > $maxWidth) {
                $image->scaleDown(height: $maxWidth);
            }
        } else {
            if ($image->width() > $maxWidth) {
                $image->scaleDown(width: $maxWidth);
            }
        }

        // Now safe to orient — image is already small
        $image->modify(new AlignRotationModifier());

        return $image;
    }

    /**
     * Get oriented dimensions using native PHP functions (no GD memory needed)
     *
     * @return array{0: int, 1: int} [width, height] after EXIF orientation
     */
    private function getOrientedDimensions(string $filePath): array
    {
        $info = getimagesize($filePath);
        $rawWidth = $info[0] ?? 0;
        $rawHeight = $info[1] ?? 0;

        $exif = @exif_read_data($filePath);
        $orientation = $exif['Orientation'] ?? 1;

        // Orientations 5-8 involve 90°/270° rotation which swaps dimensions
        if (in_array($orientation, [5, 6, 7, 8])) {
            return [$rawHeight, $rawWidth];
        }

        return [$rawWidth, $rawHeight];
    }

    /**
     * Apply two-layer watermark: dense diagonal grid + large central text
     */
    private function applyWatermark(ImageInterface $image, float $centralOpacity, float $gridOpacity): void
    {
        $width = $image->width();
        $height = $image->height();
        $minDim = min($width, $height);

        $watermarkText = self::WATERMARK_TEXT;
        $fontPath = $this->getFontPath();

        // --- Layer 1: Dense diagonal grid of small texts ---
        $gridFontSize = (int)($minDim * 0.04);
        $gridFontSize = max($gridFontSize, 10);
        $gridColor = "rgba(50, 50, 50, $gridOpacity)";

        $cols = 4;
        $rows = 5;
        $stepX = $width / $cols;
        $stepY = $height / $rows;

        for ($row = 0 ; $row < $rows ; $row++) {
            for ($col = 0 ; $col < $cols ; $col++) {
                $posX = (int)($stepX * $col + $stepX / 2);
                $posY = (int)($stepY * $row + $stepY / 2);

                $image->text($watermarkText, $posX, $posY, function ($font) use ($gridFontSize, $gridColor, $fontPath) {
                    if ($fontPath) {
                        $font->filename($fontPath);
                    }
                    $font->size($gridFontSize);
                    $font->color($gridColor);
                    $font->angle(-30);
                    $font->align('center');
                    $font->valign('middle');
                });
            }
        }

        // --- Layer 2: Large central text ---
        $centralFontSize = (int)($minDim * 0.15);
        // Cap font size so text doesn't overflow image width (~0.6 * fontSize * charCount ≈ text width)
        $maxFontForWidth = (int)($width * 0.80 / (0.6 * mb_strlen($watermarkText)));
        $centralFontSize = min($centralFontSize, $maxFontForWidth);
        $centralFontSize = max($centralFontSize, 24);
        $centralColor = "rgba(50, 50, 50, $centralOpacity)";

        $centerX = (int)($width / 2);
        $centerY = (int)($height / 2);

        $image->text($watermarkText, $centerX, $centerY, function ($font) use ($centralFontSize, $centralColor, $fontPath) {
            if ($fontPath) {
                $font->filename($fontPath);
            }
            $font->size($centralFontSize);
            $font->color($centralColor);
            $font->angle(0);
            $font->align('center');
            $font->valign('middle');
        });
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
