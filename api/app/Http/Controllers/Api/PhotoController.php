<?php

namespace App\Http\Controllers\Api;

use App\Helpers\MimeTypes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkToggleDownloadableRequest;
use App\Http\Requests\StoreAsyncPhotoRequest;
use App\Http\Requests\UpdateSortOrderRequest;
use App\Http\Requests\UploadStatusRequest;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoUpload;
use App\Services\ImageProcessingService;
use App\Services\MinioStorageService;
use App\Traits\ClearsEventGalleriesCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PhotoController extends Controller
{
    use ClearsEventGalleriesCache;

    public function show(Photo $photo): JsonResponse
    {
        return response()->json([
            'photo' => $photo,
        ]);
    }

    public function destroy(Photo $photo): JsonResponse
    {
        $isEventGallery = $photo->gallery?->type === 'event';

        $storageService = app(MinioStorageService::class);

        $pathsToDelete = array_filter([
            $photo->resolved_storage_path,
            $photo->file_path_preview,
            $photo->file_path_thumbnail,
            $photo->file_path_hd,
        ]);

        foreach ($pathsToDelete as $path) {
            if ($path && ! str_starts_with($path, 'http')) {
                $storageService->deletePhoto($path);
            }
        }

        $photo->delete();

        if ($isEventGallery) {
            $this->clearEventGalleriesCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'Photo supprimée avec succès.',
        ]);
    }

    public function like(Photo $photo): JsonResponse
    {
        $isLiked = $photo->toggleLike();

        return response()->json([
            'success' => true,
            'is_liked' => $isLiked,
        ]);
    }

    public function download(Photo $photo, Request $request)
    {
        $token = $request->query('token');
        $gallery = $photo->gallery;

        if (! $gallery->isAccessible($token)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        if (! $photo->is_downloadable) {
            return response()->json([
                'message' => 'Cette photo n\'est pas téléchargeable.',
            ], 403);
        }

        $storageService = app(MinioStorageService::class);
        $storagePath = $photo->resolved_storage_path;

        $photo->recordDownload(
            $request->ip(),
            $request->userAgent()
        );

        // Streaming direct pour compat WebView (le mode signed URL ne fonctionne pas dans certaines WebView).
        if ($request->query('direct')) {
            $content = $storageService->getFileContent($storagePath);
            if (! $content) {
                return response()->json([
                    'message' => 'Erreur lors du téléchargement.',
                ], 500);
            }
            $extension = pathinfo($photo->file_path, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = ($photo->title ?? 'photo').'.'.$extension;
            $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
            $mimeType = MimeTypes::fromExtension($extension);

            return response($content, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => strlen($content),
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache',
            ]);
        }

        $signedUrl = $storageService->getSignedUrl($storagePath, 300);

        if (! $signedUrl) {
            return response()->json([
                'message' => 'Erreur lors de la génération du lien.',
            ], 500);
        }

        return response()->json([
            'download_url' => $signedUrl,
            'filename' => $photo->title ?? basename($photo->file_path),
        ]);
    }

    public function toggleDownloadable(Photo $photo): JsonResponse
    {
        $isDownloadable = $photo->toggleDownloadable();

        return response()->json([
            'success' => true,
            'data' => [
                'is_downloadable' => $isDownloadable,
            ],
            'message' => $isDownloadable
                ? 'Photo marquée comme téléchargeable.'
                : 'Photo retirée des téléchargeables.',
        ]);
    }

    public function bulkToggleDownloadable(BulkToggleDownloadableRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Photo::whereIn('id', $validated['photo_ids'])
            ->update(['is_downloadable' => $validated['is_downloadable']]);

        return response()->json([
            'message' => $validated['is_downloadable']
                ? 'Photos marquées comme téléchargeables.'
                : 'Photos retirées des téléchargeables.',
            'updated_count' => count($validated['photo_ids']),
        ]);
    }

    public function updateSortOrder(UpdateSortOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $cases = [];
        $bindings = [];
        $ids = [];
        foreach ($validated['photos'] as $photoData) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $photoData['id'];
            $bindings[] = (int) $photoData['sort_order'];
            $ids[] = $photoData['id'];
        }

        if (! empty($cases)) {
            $caseSql = implode(' ', $cases);
            Photo::whereIn('id', $ids)
                ->update(['sort_order' => \DB::raw("CASE id {$caseSql} END", $bindings)]);
        }

        return response()->json([
            'message' => 'Ordre mis à jour.',
        ]);
    }

    /**
     * Reçoit un chunk et le traite inline (synchrone, dans le même worker PHP-FPM).
     *
     * Le nom "Async" est historique : l'endpoint dispatch-ait un queue job. Le traitement
     * synchrone élimine la race cross-container temp-file (qui coûtait 30-60% des uploads
     * en local macOS / virtio-fs) et les soucis de workers queue avec classes stale.
     *
     * Tailles : 30 workers PHP-FPM, max_execution_time=120s, ~3-6s/photo avec Imagick,
     * memory peak ~200-300 Mo (limit 2 Go).
     */
    public function storeAsync(
        StoreAsyncPhotoRequest $request,
        Gallery $gallery,
        ImageProcessingService $imageProcessing,
        MinioStorageService $storage,
    ): JsonResponse {
        if ($gallery->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'ajouter des photos à une galerie parent. Ajoutez-les dans une sous-galerie.',
            ], 422);
        }

        $validated = $request->validated();
        $batchId = $validated['batch_id'];
        $uploads = [];
        $hasCompletedPhoto = false;

        foreach ($request->file('photos') as $file) {
            $upload = null;
            $started = microtime(true);
            try {
                $upload = PhotoUpload::create([
                    'batch_id' => $batchId,
                    'gallery_id' => $gallery->id,
                    'original_filename' => $file->getClientOriginalName(),
                    'status' => 'processing',
                ]);

                $photo = $this->processSinglePhoto($file, $gallery, $imageProcessing, $storage);

                $upload->markAsCompleted($photo->id);
                $hasCompletedPhoto = true;

                $uploads[] = [
                    'id' => $upload->id,
                    'original_filename' => $upload->original_filename,
                    'status' => 'completed',
                    'photo_id' => $photo->id,
                ];

                Log::info('storeAsync: photo processed', [
                    'upload_id' => $upload->id,
                    'gallery_id' => $gallery->id,
                    'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                ]);
            } catch (\Throwable $e) {
                // Message technique en logs, message actionnable côté user (humanizeUploadError).
                Log::error('storeAsync: inline processing failed', [
                    'gallery_id' => $gallery->id,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                ]);
                if ($upload) {
                    $friendlyMessage = $this->humanizeUploadError($e);
                    $upload->markAsFailed($friendlyMessage);
                    $uploads[] = [
                        'id' => $upload->id,
                        'original_filename' => $upload->original_filename,
                        'status' => 'failed',
                        'error_message' => $friendlyMessage,
                    ];
                }
            }
        }

        if ($gallery->type === 'event' && $hasCompletedPhoto) {
            $this->clearEventGalleriesCache();
        }

        return response()->json([
            'success' => true,
            'batch_id' => $batchId,
            'uploads' => $uploads,
        ]);
    }

    /** Mappe l'exception technique en message court et actionnable côté UI. */
    private function humanizeUploadError(\Throwable $e): string
    {
        $msg = $e->getMessage();
        $lower = mb_strtolower($msg);

        return match (true) {
            str_contains($lower, 'memory') || str_contains($lower, 'allowed memory size') => 'Image trop volumineuse pour être traitée. Essaie un format compressé.',
            str_contains($lower, 'minio') || str_contains($lower, 's3') || str_contains($lower, 'curl') => 'Stockage temporairement indisponible. Clique sur « Réessayer ».',
            str_contains($lower, 'mime') || str_contains($lower, 'format') || str_contains($lower, 'mimes') => 'Format non supporté. Formats acceptés : JPEG, PNG, WEBP, MP4.',
            str_contains($lower, 'decode') || str_contains($lower, 'décodage') || str_contains($lower, 'corrupt') => 'Image corrompue ou illisible.',
            str_contains($lower, 'galerie non trouvée') || str_contains($lower, 'gallery') => 'Galerie introuvable, recharge la page.',
            str_contains($lower, 'permission') || str_contains($lower, 'denied') => 'Accès refusé au stockage. Contacter l\'administrateur.',
            default => 'Erreur lors du traitement. Clique sur « Réessayer ».',
        };
    }

    private function processSinglePhoto(
        \Illuminate\Http\UploadedFile $file,
        Gallery $gallery,
        ImageProcessingService $imageProcessing,
        MinioStorageService $storage,
    ): Photo {
        $isVideo = str_starts_with($file->getMimeType(), 'video/');

        if ($isVideo) {
            $result = $storage->uploadPhoto($file, $gallery->id);
            if (! $result) {
                throw new \RuntimeException("Échec de l'upload vidéo vers MinIO");
            }

            return $gallery->photos()->create([
                'file_path' => $result['path'],
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'is_video' => true,
                'is_downloadable' => false,
                'is_processed' => true,
                'metadata' => [
                    'original_filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'storage_path' => $result['path'],
                ],
            ]);
        }

        $result = $imageProcessing->processUploadedPhoto($file, $gallery->id);
        if (! $result) {
            throw new \RuntimeException("Échec du traitement de l'image");
        }

        return $gallery->photos()->create([
            'file_path' => $result['hd_path'],
            'file_path_hd' => $result['hd_path'],
            'file_path_preview' => $result['preview_path'],
            'file_path_thumbnail' => $result['thumbnail_path'],
            'is_processed' => true,
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'is_downloadable' => false,
            'metadata' => [
                'original_filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'storage_path' => $result['hd_path'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
            ],
        ]);
    }

    /** `include_uploads=false` retourne uniquement les compteurs agrégés (gros batches). */
    public function uploadStatus(UploadStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = PhotoUpload::getBatchStatus(
            $validated['batch_id'],
            $validated['include_uploads'] ?? true
        );

        return response()->json($status);
    }
}
