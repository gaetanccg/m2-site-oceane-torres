<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function show(Photo $photo): JsonResponse
    {
        return response()->json([
            'photo' => $photo,
        ]);
    }

    public function store(Request $request, Gallery $gallery): JsonResponse
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,webp', 'max:51200'], // 50MB max per file
        ]);

        $storageService = new SupabaseStorageService();
        $uploadedPhotos = [];
        $errors = [];

        foreach ($request->file('photos') as $file) {
            try {
                // Upload to Supabase
                $result = $storageService->uploadPhoto($file, $gallery->id);

                if ($result) {
                    // Create photo entry in database
                    $photo = $gallery->photos()->create([
                        'file_path' => $result['url'],
                        'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                        'is_downloadable' => false,
                        'metadata' => [
                            'original_filename' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'supabase_path' => $result['path'],
                        ],
                    ]);

                    $uploadedPhotos[] = $photo;
                } else {
                    $errors[] = "Erreur lors de l'upload de {$file->getClientOriginalName()}";
                }
            } catch (\Exception $e) {
                $errors[] = "Erreur: {$file->getClientOriginalName()} - {$e->getMessage()}";
            }
        }

        return response()->json([
            'success' => count($uploadedPhotos) > 0,
            'data' => $uploadedPhotos,
            'errors' => $errors,
            'message' => count($uploadedPhotos) . ' photo(s) uploadée(s) avec succès.',
        ], 201);
    }

    public function destroy(Photo $photo): JsonResponse
    {
        // Delete from Supabase storage
        $supabasePath = $photo->metadata['supabase_path'] ?? null;
        if ($supabasePath) {
            $storageService = new SupabaseStorageService();
            $storageService->deletePhoto($supabasePath);
        }

        $photo->delete();

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

        if (!$gallery->isAccessible($token)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        if (!$photo->is_downloadable) {
            return response()->json([
                'message' => 'Cette photo n\'est pas téléchargeable.',
            ], 403);
        }

        $storageService = new SupabaseStorageService();
        $signedUrl = $storageService->getSignedUrl($photo->file_path, 300);

        if (!$signedUrl) {
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
}
