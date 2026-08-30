<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DownloadableGalleryResource;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Les galeries, cote public : acces par code de partage, par jeton de telechargement, et le
 * ZIP.
 *
 * Trois chemins d'acces par CREDENTIAL, et rien d'autre. Ce controleur n'ecrit pas — la
 * seule modification qu'il provoque est le compteur de vues. L'administration est dans
 * `Api\Admin\GalleryController`.
 */
class GalleryController extends Controller
{
    private const ZIP_MAX_PHOTOS = 500;

    public function showByShareCode(string $code): JsonResponse
    {
        $gallery = Gallery::byShareCode($code)->first();

        if (! $gallery) {
            return response()->json([
                'message' => 'Code invalide.',
            ], 404);
        }

        $gallery->recordView();

        $gallery->load([
            'photos' => function ($query) {
                $query->ordered();
            },
            'galleryProductTypes',
            'schoolSession:id,gallery_message,closed_at',
        ]);

        return response()->json([
            'gallery' => new GalleryResource($gallery),
            'mode' => 'protected',
            'available_product_types' => $gallery->getAvailableProductTypes(),
            'pack_pricing' => $gallery->getPackPricing(),
            'school_message' => $gallery->schoolSession?->gallery_message,
            'school_closed' => (bool) $gallery->schoolSession?->isClosed(),
        ]);
    }

    public function showDownloadableByToken(string $token): JsonResponse
    {
        $gallery = Gallery::byAccessToken($token)->first();

        if (! $gallery) {
            return response()->json([
                'message' => 'Lien invalide.',
            ], 404);
        }

        $gallery->recordView();

        $gallery->load([
            'photos' => function ($query) {
                $query->downloadable()->ordered();
            },
        ]);

        // Expose the clean thumbnail URL only here (never in shop galleries, to avoid
        // leaking the non-watermarked derivative). Set the gallery relation so the
        // accessor's token fallback doesn't trigger an N+1 query.
        $gallery->photos->each(function ($photo) use ($gallery) {
            $photo->setRelation('gallery', $gallery);
            $photo->append('clean_thumbnail_url');
        });

        return response()->json([
            'gallery' => new DownloadableGalleryResource($gallery),
            'mode' => 'download',
        ]);
    }

    /**
     * Stream a ZIP of every downloadable photo directly to the browser.
     *
     * The archive is streamed on the fly (bounded memory, first bytes sent
     * immediately) instead of being built entirely in memory — the previous
     * addFromString() approach exhausted PHP's memory_limit on large galleries,
     * killing the worker before HandleCors could attach headers (surfacing as a
     * CORS error client-side). The frontend downloads this via a direct
     * navigation, so the request is not an XHR and CORS never applies.
     */
    public function downloadZip(Gallery $gallery, Request $request): StreamedResponse
    {
        $token = $request->query('token');
        $user = $request->user();

        abort_unless(
            Gate::forUser($user)->allows('download', [$gallery, $token]),
            403,
            'Accès non autorisé.'
        );

        $downloadableCount = $gallery->photos()->downloadable()->count();
        $photos = $gallery->photos()->downloadable()->limit(self::ZIP_MAX_PHOTOS)->get();

        abort_if($photos->isEmpty(), 404, 'Aucune photo téléchargeable.');

        $zipName = 'galerie_'.(Str::slug($gallery->title) ?: 'photos').'.zip';

        if ($downloadableCount > self::ZIP_MAX_PHOTOS) {
            $zipName = 'galerie_'.(Str::slug($gallery->title) ?: 'photos')
                .'_1-'.self::ZIP_MAX_PHOTOS.'-sur-'.$downloadableCount.'.zip';

            \Log::warning('Gallery ZIP truncated by the photo cap', [
                'gallery_id' => $gallery->id,
                'downloadable' => $downloadableCount,
                'included' => self::ZIP_MAX_PHOTOS,
            ]);
        }
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        return response()->streamDownload(function () use ($photos, $ip, $userAgent) {
            $zip = new ZipStream(
                sendHttpHeaders: false,
                defaultCompressionMethod: CompressionMethod::STORE,
            );

            $usedNames = [];

            foreach ($photos as $index => $photo) {
                $stream = Storage::disk('minio')->readStream($photo->resolved_storage_path);
                if ($stream === null) {
                    continue;
                }

                $extension = pathinfo($photo->file_path, PATHINFO_EXTENSION) ?: 'jpg';
                $base = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $photo->title ?: 'photo_'.($index + 1));
                $entryName = $base.'.'.$extension;

                // Ensure unique entry names inside the archive
                $suffix = 1;
                while (isset($usedNames[$entryName])) {
                    $entryName = $base.'_'.$suffix.'.'.$extension;
                    $suffix++;
                }
                $usedNames[$entryName] = true;

                $zip->addFileFromStream($entryName, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $photo->recordDownload($ip, $userAgent);
            }

            $zip->finish();
        }, $zipName, [
            'Content-Type' => 'application/zip',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
