<?php

namespace App\Services;

use App\Jobs\ProcessPhotoJob;
use App\Models\Gallery;
use App\Models\GalleryProductType;
use App\Models\PackTier;
use App\Models\PhotoUpload;
use App\Models\SchoolSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SchoolSessionService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const IGNORED_FILES = ['.DS_Store', 'Thumbs.db', 'desktop.ini'];

    private const IGNORED_DIRS = ['__MACOSX', '.Spotlight-V100', '.Trashes'];

    public function __construct(
        private ImageProcessingService $imageProcessingService,
        private MinioStorageService $storageService,
    ) {}

    /**
     * Extract the ZIP and return the parsed directory structure.
     *
     * @return array{classes: array<string, array{shared_photos: string[], children: array<string, string[]>}>}
     */
    public function extractAndParseZip(SchoolSession $session): array
    {
        $zipFullPath = Storage::disk('local')->path($session->zip_path);

        if (! file_exists($zipFullPath)) {
            throw new \RuntimeException('Fichier ZIP introuvable: '.$session->zip_path);
        }

        $zip = new \ZipArchive;
        $result = $zip->open($zipFullPath);

        if ($result !== true) {
            throw new \RuntimeException('Impossible d\'ouvrir le ZIP (code: '.$result.')');
        }

        $extractDir = storage_path('app/private/temp/school-sessions/'.$session->id.'/extracted');

        if (! is_dir($extractDir)) {
            mkdir($extractDir, 0755, true);
        }

        $zip->extractTo($extractDir);
        $zip->close();

        return $this->parseExtractedDirectory($extractDir);
    }

    /**
     * Parse the extracted directory structure into classes/children.
     */
    private function parseExtractedDirectory(string $rootDir): array
    {
        // Detect wrapper folder: if root has exactly one directory and no image files,
        // treat its contents as the real root.
        $rootDir = $this->resolveRootDirectory($rootDir);

        $classes = [];

        foreach ($this->listDirectories($rootDir) as $classDir) {
            $className = basename($classDir);

            if ($this->isIgnoredDirectory($className)) {
                continue;
            }

            $sharedPhotos = [];
            $children = [];

            foreach ($this->listDirectories($classDir) as $subDir) {
                $subDirName = basename($subDir);

                if ($this->isIgnoredDirectory($subDirName)) {
                    continue;
                }

                if (str_starts_with($subDirName, '_')) {
                    // Shared photos directory (e.g., _classe/)
                    $sharedPhotos = array_merge($sharedPhotos, $this->listImageFiles($subDir));
                } else {
                    // Child directory = one gallery
                    $images = $this->listImageFiles($subDir);
                    if (! empty($images)) {
                        $childName = $this->sanitizeGalleryTitle($subDirName);
                        $children[$childName] = $images;
                    }
                }
            }

            if (! empty($children)) {
                $classes[$className] = [
                    'shared_photos' => $sharedPhotos,
                    'children' => $children,
                ];
            }
        }

        if (empty($classes)) {
            throw new \RuntimeException('Aucune structure classe/enfant valide trouvée dans le ZIP. Vérifiez l\'arborescence des dossiers.');
        }

        return ['classes' => $classes];
    }

    /**
     * If the extracted root contains a single folder (common with ZIP tools),
     * unwrap it and treat the subfolder as root.
     */
    private function resolveRootDirectory(string $dir): string
    {
        $entries = array_filter(scandir($dir), fn ($e) => $e !== '.' && $e !== '..');
        $dirs = array_filter($entries, fn ($e) => is_dir($dir.'/'.$e) && ! $this->isIgnoredDirectory($e));
        $files = array_filter($entries, fn ($e) => is_file($dir.'/'.$e) && $this->isImageFile($e));

        if (count($dirs) === 1 && count($files) === 0) {
            return $dir.'/'.reset($dirs);
        }

        return $dir;
    }

    /**
     * Process shared (class) photos once and return Photo attribute arrays.
     *
     * @param  string[]  $photoPaths  Absolute paths to shared photos
     * @return array<array{file_path: string, file_path_hd: string, file_path_preview: string, file_path_thumbnail: string, title: string, is_processed: bool, is_downloadable: bool, metadata: array}>
     */
    public function processSharedPhotos(SchoolSession $session, array $photoPaths): array
    {
        $sharedPrefix = 'school-sessions/'.$session->id.'/shared';
        $attributes = [];

        foreach ($photoPaths as $photoPath) {
            $filename = basename($photoPath);
            $mimeType = mime_content_type($photoPath) ?: 'image/jpeg';

            $tempFile = new UploadedFile($photoPath, $filename, $mimeType, null, true);

            $result = $this->imageProcessingService->processUploadedPhoto($tempFile, $sharedPrefix);

            if (! $result) {
                Log::warning('SchoolSession: failed to process shared photo', [
                    'session_id' => $session->id,
                    'photo' => $filename,
                ]);

                continue;
            }

            $attributes[] = [
                'file_path' => $result['hd_path'],
                'file_path_hd' => $result['hd_path'],
                'file_path_preview' => $result['preview_path'],
                'file_path_thumbnail' => $result['thumbnail_path'],
                'is_processed' => true,
                'is_downloadable' => false,
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'metadata' => [
                    'original_filename' => $filename,
                    'mime_type' => $mimeType,
                    'storage_path' => $result['hd_path'],
                    'shared' => true,
                ],
            ];
        }

        return $attributes;
    }

    /**
     * Create a gallery for one child, sync product types, add shared photos,
     * and dispatch ProcessPhotoJob for each portrait.
     *
     * @param  array  $sharedPhotoAttributes  From processSharedPhotos()
     * @param  string[]  $portraitPaths  Absolute paths to portrait images
     */
    public function createChildGallery(
        SchoolSession $session,
        string $childName,
        string $batchId,
        array $sharedPhotoAttributes,
        array $portraitPaths,
        ?string $className = null,
    ): Gallery {
        // 1. Create gallery — access_token & share_code auto-generated by Gallery::boot()
        $gallery = Gallery::create([
            'title' => $childName,
            'type' => 'private',
            'school_session_id' => $session->id,
            'class_name' => $className,
            'event_date' => $session->event_date,
        ]);

        // 2. Sync product types from session config
        if (! empty($session->product_types_config)) {
            $this->syncProductTypesFromConfig($gallery, $session->product_types_config);
        }

        // 3. Create Photo records for shared class photos (same MinIO paths, no file duplication)
        foreach ($sharedPhotoAttributes as $attrs) {
            $gallery->photos()->create($attrs);
        }

        // 4. For each portrait: create PhotoUpload + copy to temp + dispatch ProcessPhotoJob
        foreach ($portraitPaths as $portraitPath) {
            $filename = basename($portraitPath);
            $mimeType = mime_content_type($portraitPath) ?: 'image/jpeg';

            $upload = PhotoUpload::create([
                'batch_id' => $batchId,
                'gallery_id' => $gallery->id,
                'original_filename' => $filename,
                'status' => 'uploading',
            ]);

            $tempPath = 'temp_uploads/'.$upload->id.'_'.$filename;
            $tempFullPath = Storage::disk('local')->path($tempPath);

            // Ensure target directory exists
            $tempDir = dirname($tempFullPath);
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // System-level copy: avoids loading file content into PHP memory (critical for 1000+ photos)
            copy($portraitPath, $tempFullPath);

            $upload->update(['status' => 'pending']);

            ProcessPhotoJob::dispatch(
                $upload->id,
                $gallery->id,
                $tempPath,
                $filename,
                $mimeType,
            );
        }

        return $gallery;
    }

    /**
     * Delete all MinIO files for a session: per-gallery folders + shared folder.
     * Must be called BEFORE database cascade delete.
     */
    public function deleteSessionFiles(SchoolSession $session): void
    {
        // Delete per-gallery MinIO folders
        foreach ($session->galleries as $gallery) {
            try {
                $this->storageService->deleteGalleryFolder($gallery->id);
            } catch (\Exception $e) {
                Log::warning('SchoolSession: failed to delete gallery MinIO folder', [
                    'gallery_id' => $gallery->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Delete shared photos folder
        try {
            $sharedPrefix = 'school-sessions/'.$session->id;
            $files = Storage::disk('minio')->allFiles($sharedPrefix);
            if (! empty($files)) {
                Storage::disk('minio')->delete($files);
            }
            Storage::disk('minio')->deleteDirectory($sharedPrefix);
        } catch (\Exception $e) {
            Log::warning('SchoolSession: failed to delete shared MinIO folder', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up extracted ZIP files from local disk.
     */
    public function cleanupExtractedFiles(SchoolSession $session): void
    {
        // Delete extracted directory
        $extractedDir = storage_path('app/private/temp/school-sessions/'.$session->id);
        if (is_dir($extractedDir)) {
            $this->deleteDirectoryRecursive($extractedDir);
        }

        // Delete ZIP file
        if ($session->zip_path && Storage::disk('local')->exists($session->zip_path)) {
            Storage::disk('local')->delete($session->zip_path);
        }
    }

    /**
     * Sync product types onto a gallery from a JSON config array.
     * Same logic as SyncsProductTypes trait but works with a raw array.
     */
    private function syncProductTypesFromConfig(Gallery $gallery, array $productTypes): void
    {
        foreach ($productTypes as $config) {
            $gpt = GalleryProductType::create([
                'gallery_id' => $gallery->id,
                'product_type' => $config['product_type'],
                'is_enabled' => $config['is_enabled'],
                'price' => $config['price'] ?? null,
            ]);

            if (! empty($config['tiers'])) {
                foreach ($config['tiers'] as $tier) {
                    PackTier::create([
                        'gallery_product_type_id' => $gpt->id,
                        'min_quantity' => $tier['min_quantity'],
                        'unit_price' => $tier['unit_price'],
                    ]);
                }
            }
        }
    }

    private function listDirectories(string $dir): array
    {
        $dirs = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $dir.'/'.$entry;
            if (is_dir($fullPath)) {
                $dirs[] = $fullPath;
            }
        }
        sort($dirs);

        return $dirs;
    }

    private function listImageFiles(string $dir): array
    {
        $files = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $dir.'/'.$entry;
            if (is_file($fullPath) && $this->isImageFile($entry)) {
                $files[] = $fullPath;
            }
        }
        sort($files);

        return $files;
    }

    private function isImageFile(string $filename): bool
    {
        if (in_array($filename, self::IGNORED_FILES, true)) {
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, self::ALLOWED_EXTENSIONS, true);
    }

    private function isIgnoredDirectory(string $name): bool
    {
        return in_array($name, self::IGNORED_DIRS, true) || str_starts_with($name, '.');
    }

    private function sanitizeGalleryTitle(string $dirName): string
    {
        // Trim whitespace and normalize encoding
        $title = trim($dirName);

        // Detect and convert from CP437/CP850 (Windows ZIP encoding) if needed
        if (! mb_check_encoding($title, 'UTF-8')) {
            $converted = @iconv('CP437', 'UTF-8//TRANSLIT', $title);
            if ($converted !== false) {
                $title = $converted;
            }
        }

        return $title;
    }

    private function deleteDirectoryRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $dir.'/'.$entry;
            if (is_dir($fullPath)) {
                $this->deleteDirectoryRecursive($fullPath);
            } else {
                unlink($fullPath);
            }
        }
        rmdir($dir);
    }
}
