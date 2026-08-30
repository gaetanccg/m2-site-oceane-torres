<?php

namespace App\Http\Controllers\Api;

use App\Helpers\MimeTypes;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Services\MinioStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les photos, cote public : « j'aime » et le telechargement par une cliente.
 *
 * L'administration est dans `Api\Admin\PhotoController`. Ce controleur-ci ne modifie
 * qu'un compteur de « j'aime » ; il ne televerse ni ne supprime rien.
 */
class PhotoController extends Controller
{
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

        // Sert le fichier d'origine : le jeton est exige, meme sur une galerie `public`.
        if (! $gallery->grantsCleanDerivatives($token)) {
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
}
