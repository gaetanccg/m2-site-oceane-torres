<?php

namespace App\Http\Controllers\Api;

use App\Helpers\MimeTypes;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPhotoJob;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoUpload;
use App\Services\ImageProcessingService;
use App\Services\MinioStorageService;
use App\Traits\ClearsEventGalleriesCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    use ClearsEventGalleriesCache;

    public function show(Photo $photo): JsonResponse
    {
        return response()->json([
            'photo' => $photo,
        ]);
    }

    public function store(Request $request, Gallery $gallery): JsonResponse
    {
        // Block upload on parent galleries (galleries with children)
        if ($gallery->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'ajouter des photos à une galerie parent. Ajoutez-les dans une sous-galerie.',
            ], 422);
        }

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi', 'max:51200'], // 50MB max per file
        ]);

        $imageProcessingService = new ImageProcessingService;
        $uploadedPhotos = [];
        $errors = [];

        foreach ($request->file('photos') as $file) {
            try {
                $mimeType = $file->getMimeType();
                $isVideo = str_starts_with($mimeType, 'video/');

                if ($isVideo) {
                    // Videos: upload directly without processing
                    $storageService = new MinioStorageService;
                    $result = $storageService->uploadPhoto($file, $gallery->id);

                    if ($result) {
                        $photo = $gallery->photos()->create([
                            'file_path' => $result['path'],
                            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                            'is_video' => true,
                            'is_downloadable' => false,
                            'is_processed' => true,
                            'metadata' => [
                                'original_filename' => $file->getClientOriginalName(),
                                'size' => $file->getSize(),
                                'mime_type' => $mimeType,
                                'storage_path' => $result['path'],
                            ],
                        ]);
                        $uploadedPhotos[] = $photo;
                    } else {
                        $errors[] = "Erreur lors de l'upload de {$file->getClientOriginalName()}";
                    }
                } else {
                    // Images: process with watermarks and create versions
                    $result = $imageProcessingService->processUploadedPhoto($file, $gallery->id);

                    if ($result) {
                        $photo = $gallery->photos()->create([
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
                                'mime_type' => $mimeType,
                                'storage_path' => $result['hd_path'],
                                'width' => $result['width'] ?? null,
                                'height' => $result['height'] ?? null,
                            ],
                        ]);
                        $uploadedPhotos[] = $photo;
                    } else {
                        $errors[] = "Erreur lors du traitement de {$file->getClientOriginalName()}";
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Erreur: {$file->getClientOriginalName()} - {$e->getMessage()}";
            }
        }

        // Clear event galleries cache if this is an event gallery
        if ($gallery->type === 'event' && count($uploadedPhotos) > 0) {
            $this->clearEventGalleriesCache();
        }

        return response()->json([
            'success' => count($uploadedPhotos) > 0,
            'data' => $uploadedPhotos,
            'errors' => $errors,
            'message' => count($uploadedPhotos).' photo(s) uploadée(s) avec succès.',
        ], 201);
    }

    public function destroy(Photo $photo): JsonResponse
    {
        // Check if this is an event gallery photo before deleting
        $isEventGallery = $photo->gallery?->type === 'event';

        // Delete all versions from MinIO storage (original, preview, thumbnail)
        $storageService = new MinioStorageService;

        // Collect all file paths to delete
        $pathsToDelete = array_filter([
            // Original/HD path from metadata or file_path
            $photo->metadata['storage_path'] ?? $photo->metadata['supabase_path'] ?? $photo->file_path,
            // Preview path
            $photo->file_path_preview,
            // Thumbnail path
            $photo->file_path_thumbnail,
            // HD path if different
            $photo->file_path_hd,
        ]);

        foreach ($pathsToDelete as $path) {
            if ($path && ! str_starts_with($path, 'http')) {
                $storageService->deletePhoto($path);
            }
        }

        $photo->delete();

        // Clear event galleries cache if needed
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

        $storageService = new MinioStorageService;
        $storagePath = $photo->metadata['storage_path'] ?? $photo->metadata['supabase_path'] ?? $photo->file_path;

        // Track the download
        $photo->recordDownload(
            $request->ip(),
            $request->userAgent()
        );

        // Direct file streaming for WebView compatibility
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

        // Existing JSON response with signed URL
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

    public function bulkToggleDownloadable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array'],
            'photo_ids.*' => ['exists:photos,id'],
            'is_downloadable' => ['required', 'boolean'],
        ]);

        Photo::whereIn('id', $validated['photo_ids'])
            ->update(['is_downloadable' => $validated['is_downloadable']]);

        return response()->json([
            'message' => $validated['is_downloadable']
                ? 'Photos marquées comme téléchargeables.'
                : 'Photos retirées des téléchargeables.',
            'updated_count' => count($validated['photo_ids']),
        ]);
    }

    public function updateSortOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photos' => ['required', 'array'],
            'photos.*.id' => ['required', 'exists:photos,id'],
            'photos.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['photos'] as $photoData) {
            Photo::where('id', $photoData['id'])
                ->update(['sort_order' => $photoData['sort_order']]);
        }

        return response()->json([
            'message' => 'Ordre mis à jour.',
        ]);
    }

    /**
     * Store photos asynchronously via job queue
     * Accepts chunks of up to 15 photos at a time
     */
    public function storeAsync(Request $request, Gallery $gallery): JsonResponse
    {
        // Block upload on parent galleries (galleries with children)
        if ($gallery->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'ajouter des photos à une galerie parent. Ajoutez-les dans une sous-galerie.',
            ], 422);
        }

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:15'],
            'photos.*' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi', 'max:51200'],
            'batch_id' => ['required', 'string'],
        ]);

        $batchId = $validated['batch_id'];
        $uploads = [];

        foreach ($request->file('photos') as $file) {
            try {
                // Create PhotoUpload record
                $upload = PhotoUpload::create([
                    'batch_id' => $batchId,
                    'gallery_id' => $gallery->id,
                    'original_filename' => $file->getClientOriginalName(),
                    'status' => 'uploading',
                ]);

                // Save file to temp storage
                $tempPath = 'temp_uploads/'.$upload->id.'_'.$file->getClientOriginalName();
                Storage::disk('local')->put($tempPath, file_get_contents($file->getRealPath()));

                // Update status to pending and dispatch job
                $upload->update(['status' => 'pending']);

                ProcessPhotoJob::dispatch(
                    $upload->id,
                    $gallery->id,
                    $tempPath,
                    $file->getClientOriginalName(),
                    $file->getMimeType()
                );

                $uploads[] = [
                    'id' => $upload->id,
                    'original_filename' => $upload->original_filename,
                    'status' => $upload->status,
                ];
            } catch (\Exception $e) {
                // If upload fails, mark as failed
                if (isset($upload)) {
                    $upload->markAsFailed($e->getMessage());
                    $uploads[] = [
                        'id' => $upload->id,
                        'original_filename' => $upload->original_filename,
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'batch_id' => $batchId,
            'uploads' => $uploads,
        ]);
    }

    /**
     * Get upload status for a batch
     */
    public function uploadStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'string'],
        ]);

        $status = PhotoUpload::getBatchStatus($validated['batch_id']);

        return response()->json($status);
    }
}
