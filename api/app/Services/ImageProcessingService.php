<?php

namespace App\Services;

use App\Helpers\MimeTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Modifiers\AlignRotationModifier;

class ImageProcessingService
{
    private ImageManager $rawManager;

    private MinioStorageService $storageService;

    private string $disk = 'minio';

    private const PREVIEW_MAX_WIDTH = 2560;

    private const THUMBNAIL_MAX_WIDTH = 600;

    private const PREVIEW_QUALITY = 93;

    private const THUMBNAIL_QUALITY = 90;

    private const WATERMARK_TEXT = '©Oceane Torres';

    private const PREVIEW_CENTRAL_OPACITY = 0.5;

    private const THUMBNAIL_CENTRAL_OPACITY = 0.5;

    private const PREVIEW_GRID_OPACITY = 0.8;

    private const THUMBNAIL_GRID_OPACITY = 0.7;

    private const WATERMARK_FONT = 'fonts/Amsterdam.ttf';

    private const USE_IMAGICK_DRIVER = true;

    /**
     * Pre-render the watermark as a transparent layer once per (width, height, opacities)
     * and re-use it across photos. Disabled: GD's transparent-canvas behavior is unreliable;
     * enable once visual output has been validated.
     */
    private const USE_WATERMARK_LAYER_CACHE = false;

    private const WATERMARK_LAYER_CACHE_LIMIT = 8;

    /** @var array<string, ImageInterface> */
    private static array $watermarkLayerCache = [];

    public function __construct(?MinioStorageService $storageService = null)
    {
        $driver = self::USE_IMAGICK_DRIVER && class_exists(\Imagick::class)
            ? new ImagickDriver
            : new GdDriver;

        $this->rawManager = new ImageManager($driver, autoOrientation: false);
        $this->storageService = $storageService ?? new MinioStorageService;
    }

    public function processUploadedPhoto(UploadedFile $file, string $galleryId): ?array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $uuid = (string) Str::uuid();
        $filename = "{$uuid}.{$extension}";

        try {
            $originalPath = "{$galleryId}/original/{$filename}";
            $previewPath = "{$galleryId}/preview/{$filename}";
            $thumbnailPath = "{$galleryId}/thumbnail/{$filename}";

            $realPath = $file->getRealPath();
            $mimeType = $file->getMimeType();

            $this->uploadOriginalStream($file, $originalPath);

            [$originalWidth, $originalHeight] = $this->getOrientedDimensions($realPath);

            $this->generatePreviewAndThumbnail($realPath, $extension, $mimeType, $previewPath, $thumbnailPath);

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

    public function processExistingPhoto(string $originalPath, string $galleryId): ?array
    {
        try {
            $content = $this->storageService->getFileContent($originalPath);
            if (! $content) {
                Log::error('Could not retrieve original file', ['path' => $originalPath]);

                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $uuid = (string) Str::uuid();
            $filename = "{$uuid}.{$extension}";

            $mimeType = MimeTypes::fromExtension($extension);

            $newOriginalPath = "{$galleryId}/original/{$filename}";
            $previewPath = "{$galleryId}/preview/{$filename}";
            $thumbnailPath = "{$galleryId}/thumbnail/{$filename}";

            $this->uploadContent($newOriginalPath, $content, $mimeType);

            $this->generatePreviewAndThumbnail($content, $extension, $mimeType, $previewPath, $thumbnailPath);

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

    public function generatePreviewOnTheFly(string $originalPath): ?string
    {
        try {
            $content = $this->storageService->getFileContent($originalPath);
            if (! $content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $image = $this->rawManager->read($content);
            $this->scaleDownPreservingOrientation($image, self::PREVIEW_MAX_WIDTH);
            $image->modify(new AlignRotationModifier);
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

    public function generateThumbnailOnTheFly(string $originalPath): ?string
    {
        try {
            $content = $this->storageService->getFileContent($originalPath);
            if (! $content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $image = $this->rawManager->read($content);
            $this->scaleDownPreservingOrientation($image, self::THUMBNAIL_MAX_WIDTH);
            $image->modify(new AlignRotationModifier);
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

    /** Preview without watermark, used for downloadable galleries. */
    public function generateCleanPreviewOnTheFly(string $originalPath): ?string
    {
        try {
            $content = $this->storageService->getFileContent($originalPath);
            if (! $content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $image = $this->rawManager->read($content);
            $this->scaleDownPreservingOrientation($image, self::PREVIEW_MAX_WIDTH);
            $image->modify(new AlignRotationModifier);

            return $this->encodeImage($image, $extension, self::PREVIEW_QUALITY);
        } catch (\Exception $e) {
            Log::error('On-the-fly clean preview generation failed', [
                'error' => $e->getMessage(),
                'path' => $originalPath,
            ]);

            return null;
        }
    }

    /** Small thumbnail without watermark, used for the downloadable gallery grid. */
    public function generateCleanThumbnailOnTheFly(string $originalPath): ?string
    {
        try {
            $content = $this->storageService->getFileContent($originalPath);
            if (! $content) {
                return null;
            }

            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $image = $this->rawManager->read($content);
            $this->scaleDownPreservingOrientation($image, self::THUMBNAIL_MAX_WIDTH);
            $image->modify(new AlignRotationModifier);

            return $this->encodeImage($image, $extension, self::THUMBNAIL_QUALITY);
        } catch (\Exception $e) {
            Log::error('On-the-fly clean thumbnail generation failed', [
                'error' => $e->getMessage(),
                'path' => $originalPath,
            ]);

            return null;
        }
    }

    /**
     * Generate a clean (no-watermark) thumbnail from the original and persist it on
     * MinIO, so downloadable galleries can serve it via a direct signed URL instead
     * of regenerating it on-the-fly through PHP-FPM on every view.
     */
    public function generateAndStoreCleanThumbnail(string $originalPath, string $targetPath, string $mimeType): bool
    {
        $content = $this->generateCleanThumbnailOnTheFly($originalPath);
        if (! $content) {
            return false;
        }

        $this->uploadContent($targetPath, $content, $mimeType);

        return true;
    }

    /**
     * Decode the source once, derive preview + thumbnail from a single scaled buffer.
     * Memory peak per photo ~17 MB instead of ~260 MB on a 7000×4600 source (was decoding twice).
     */
    private function generatePreviewAndThumbnail(
        mixed $source,
        string $extension,
        string $mimeType,
        string $previewPath,
        string $thumbnailPath,
    ): void {
        $preview = $this->rawManager->read($source);
        $this->scaleDownPreservingOrientation($preview, self::PREVIEW_MAX_WIDTH);
        $preview->modify(new AlignRotationModifier);

        // Branch BEFORE watermarking — sinon le watermark dimensionné pour 2560px se réduit
        // à ~150px sur le thumbnail et devient illisible.
        $thumbnail = clone $preview;
        $thumbnail->scaleDown(width: self::THUMBNAIL_MAX_WIDTH);

        $this->applyWatermark($preview, self::PREVIEW_CENTRAL_OPACITY, self::PREVIEW_GRID_OPACITY);
        $previewContent = $this->encodeImage($preview, $extension, self::PREVIEW_QUALITY);
        $this->uploadContent($previewPath, $previewContent, $mimeType);
        unset($preview, $previewContent);

        $this->applyWatermark($thumbnail, self::THUMBNAIL_CENTRAL_OPACITY, self::THUMBNAIL_GRID_OPACITY);
        $thumbnailContent = $this->encodeImage($thumbnail, $extension, self::THUMBNAIL_QUALITY);
        $this->uploadContent($thumbnailPath, $thumbnailContent, $mimeType);
        unset($thumbnail, $thumbnailContent);
    }

    /**
     * Stream-upload via putFileAs : évite de charger les 20-35 MB en mémoire PHP.
     * Multipart S3 au-delà de 5 MB.
     */
    private function uploadOriginalStream(UploadedFile $file, string $originalPath): void
    {
        Storage::disk($this->disk)->putFileAs(
            dirname($originalPath),
            $file,
            basename($originalPath),
            ['ContentType' => $file->getMimeType()]
        );
    }

    /**
     * Scale first (small buffer), then rotate — l'inverse force GD à allouer 3× le full-size
     * et déclenche OOM sur les portraits.
     */
    private function scaleDownPreservingOrientation(ImageInterface $image, int $maxWidth): void
    {
        $orientation = $image->exif('IFD0.Orientation');
        $willTranspose = in_array($orientation, [5, 6, 7, 8]);

        if ($willTranspose) {
            if ($image->height() > $maxWidth) {
                $image->scaleDown(height: $maxWidth);
            }
        } else {
            if ($image->width() > $maxWidth) {
                $image->scaleDown(width: $maxWidth);
            }
        }
    }

    /**
     * @return array{0: int, 1: int} [width, height] après application de l'orientation EXIF
     */
    private function getOrientedDimensions(string $filePath): array
    {
        $info = getimagesize($filePath);
        $rawWidth = $info[0] ?? 0;
        $rawHeight = $info[1] ?? 0;

        $exif = @exif_read_data($filePath);
        $orientation = $exif['Orientation'] ?? 1;

        // Orientations 5-8 : rotation 90°/270° → dimensions inversées.
        if (in_array($orientation, [5, 6, 7, 8])) {
            return [$rawHeight, $rawWidth];
        }

        return [$rawWidth, $rawHeight];
    }

    private function applyWatermark(ImageInterface $image, float $centralOpacity, float $gridOpacity): void
    {
        $width = $image->width();
        $height = $image->height();

        if (self::USE_WATERMARK_LAYER_CACHE) {
            try {
                $layer = $this->getOrBuildWatermarkLayer($width, $height, $gridOpacity, $centralOpacity);
                $image->place($layer, 'top-left', 0, 0);

                return;
            } catch (\Throwable $e) {
                Log::warning('Watermark layer caching failed, falling back to inline drawing', [
                    'error' => $e->getMessage(),
                    'width' => $width,
                    'height' => $height,
                ]);
            }
        }

        $this->drawWatermarkOn($image, $width, $height, $gridOpacity, $centralOpacity);
    }

    private function getOrBuildWatermarkLayer(int $width, int $height, float $gridOpacity, float $centralOpacity): ImageInterface
    {
        $key = "{$width}x{$height}_g{$gridOpacity}_c{$centralOpacity}";

        if (! isset(self::$watermarkLayerCache[$key])) {
            // FIFO eviction : empêche un worker qui voit beaucoup de ratios différents de fuir.
            if (count(self::$watermarkLayerCache) >= self::WATERMARK_LAYER_CACHE_LIMIT) {
                self::$watermarkLayerCache = array_slice(self::$watermarkLayerCache, 1, null, true);
            }
            self::$watermarkLayerCache[$key] = $this->buildWatermarkLayer($width, $height, $gridOpacity, $centralOpacity);
        }

        // Clone : le cache ne doit pas être muté par place() côté appelant.
        return clone self::$watermarkLayerCache[$key];
    }

    private function buildWatermarkLayer(int $width, int $height, float $gridOpacity, float $centralOpacity): ImageInterface
    {
        $layer = $this->rawManager->create($width, $height)->fill('rgba(0, 0, 0, 0)');

        $this->drawWatermarkOn($layer, $width, $height, $gridOpacity, $centralOpacity);

        return $layer;
    }

    private function drawWatermarkOn(ImageInterface $image, int $width, int $height, float $gridOpacity, float $centralOpacity): void
    {
        $minDim = min($width, $height);
        $watermarkText = self::WATERMARK_TEXT;
        $fontPath = $this->getFontPath();

        $gridFontSize = (int) ($minDim * 0.04);
        $gridFontSize = max($gridFontSize, 10);
        $gridColor = "rgba(50, 50, 50, $gridOpacity)";

        $cols = 4;
        $rows = 5;
        $stepX = $width / $cols;
        $stepY = $height / $rows;

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $posX = (int) ($stepX * $col + $stepX / 2);
                $posY = (int) ($stepY * $row + $stepY / 2);

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

        $centralFontSize = (int) ($minDim * 0.15);
        // Cap pour éviter que le texte déborde : ~0.6 × fontSize × charCount ≈ largeur texte.
        $maxFontForWidth = (int) ($width * 0.80 / (0.6 * mb_strlen($watermarkText)));
        $centralFontSize = min($centralFontSize, $maxFontForWidth);
        $centralFontSize = max($centralFontSize, 24);
        $centralColor = "rgba(50, 50, 50, $centralOpacity)";

        $centerX = (int) ($width / 2);
        $centerY = (int) ($height / 2);

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

    private function getFontPath(): ?string
    {
        $customFont = storage_path('app/'.self::WATERMARK_FONT);
        if (file_exists($customFont)) {
            return $customFont;
        }

        $systemFonts = [
            // Alpine Linux (ttf-dejavu)
            '/usr/share/fonts/ttf-dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/ttf-dejavu/DejaVuSans-Bold.ttf',
            // Debian/Ubuntu
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            // Autres Linux
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ];

        foreach ($systemFonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        // macOS
        $macFonts = [
            '/Library/Fonts/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
        ];

        foreach ($macFonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        Log::warning('No watermark font found! Watermarks will be tiny.');

        return null;
    }

    private function encodeImage(ImageInterface $image, string $extension, int $quality): string
    {
        return match ($extension) {
            'png' => $image->toPng()->toString(),
            'gif' => $image->toGif()->toString(),
            'webp' => $image->toWebp($quality)->toString(),
            default => $image->toJpeg($quality)->toString(),
        };
    }

    private function uploadContent(string $path, string $content, string $mimeType): void
    {
        Storage::disk($this->disk)->put($path, $content, [
            'ContentType' => $mimeType,
        ]);
    }

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
