<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkToggleDownloadableRequest;
use App\Http\Requests\StoreAsyncPhotoRequest;
use App\Http\Requests\UpdateSortOrderRequest;
use App\Http\Requests\UploadStatusRequest;
use App\Jobs\GenerateCleanThumbnailJob;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoUpload;
use App\Services\ImageProcessingService;
use App\Services\MinioStorageService;
use App\Traits\ClearsEventGalleriesCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Les photos, cote administration : televersement, suppression, ordre, telechargeabilite.
 *
 * Le pendant public — « j'aime » et le telechargement par une cliente — est dans
 * `Api\PhotoController`. Ce qui les separe est l'audience, pas la commodite : ce
 * controleur-ci ecrit, l'autre non.
 */
class PhotoController extends Controller
{
    use ClearsEventGalleriesCache;

    public function destroy(Photo $photo): JsonResponse
    {
        $isEventGallery = $photo->gallery?->type === 'event';

        $storageService = app(MinioStorageService::class);

        // La liste vient du modele : elle etait enumeree ici et oubliait la miniature
        // SANS FILIGRANE, qui survivait donc a la suppression.
        //
        // `exclusive` et non `storagePaths` : une photo de session scolaire est partagee par
        // les galeries de tous les eleves. Supprimer ses chemins sans compter les references
        // detruisait le fichier des autres, achats compris.
        foreach ($photo->exclusiveStoragePaths() as $path) {
            $storageService->deletePhoto($path);
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

    /**
     * Invalide la liste publique si le lot touche au moins une photo d'evenement.
     *
     * Les modeles invalident d'eux-memes, mais UN `update()` du query builder ne declenche
     * aucun evenement : les deux methodes de masse doivent le faire a la main. Une requete
     * d'existence suffit, et l'invalidation est un simple compteur.
     *
     * @param  list<string>  $photoIds
     */
    private function clearEventGalleriesCacheSiEvenement(array $photoIds): void
    {
        $touchéUnEvenement = Photo::whereIn('photos.id', $photoIds)
            ->whereHas('gallery', fn ($q) => $q->where('kind', Gallery::KIND_EVENT))
            ->exists();

        if ($touchéUnEvenement) {
            $this->clearEventGalleriesCache();
        }
    }

    public function toggleDownloadable(Photo $photo): JsonResponse
    {
        $isDownloadable = $photo->toggleDownloadable();

        // Pre-generate the clean thumbnail so the download gallery grid is served
        // from a direct MinIO URL (fast) instead of on-the-fly PHP-FPM generation.
        if ($isDownloadable && ! $photo->is_video && ! $photo->file_path_thumbnail_clean) {
            GenerateCleanThumbnailJob::dispatch($photo->id);
        }

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

        // ⚠️ Un `update()` du QUERY BUILDER ne declenche AUCUN evenement de modele :
        // l'invalidation posee dans `Photo::booted()` ne couvre pas ce chemin. Elle est donc
        // appelee ici, une fois pour le lot — ce qui vaut mieux que N fois de toute facon.
        $this->clearEventGalleriesCacheSiEvenement($validated['photo_ids']);

        // Pre-generate clean thumbnails for the newly-downloadable photos.
        if ($validated['is_downloadable']) {
            Photo::whereIn('id', $validated['photo_ids'])
                ->where('is_video', false)
                ->whereNull('file_path_thumbnail_clean')
                ->pluck('id')
                ->each(fn (string $id) => GenerateCleanThumbnailJob::dispatch($id));
        }

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

        // NB : DB::raw() n'accepte PAS de bindings en 2e argument (ils étaient
        // silencieusement ignorés → SQLSTATE[HY093], placeholders non liés et
        // décalage des paramètres). On met à jour chaque photo dans une transaction :
        // le nombre de photos réordonnées est borné par la taille d'une galerie.
        \DB::transaction(function () use ($validated) {
            foreach ($validated['photos'] as $photoData) {
                Photo::where('id', $photoData['id'])
                    ->update(['sort_order' => (int) $photoData['sort_order']]);
            }
        });

        // Meme raison qu'en masse : le query builder ne declenche pas les evenements.
        $this->clearEventGalleriesCacheSiEvenement(array_column($validated['photos'], 'id'));

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
        UploadedFile $file,
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
