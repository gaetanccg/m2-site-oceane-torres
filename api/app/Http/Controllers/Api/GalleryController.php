<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ZipArchive;

class GalleryController extends Controller
{
    public function index(): JsonResponse
    {
        $galleries = Gallery::public()
            ->active()
            ->with('photos')
            ->latest()
            ->paginate(12);

        return response()->json($galleries);
    }

    public function show(Gallery $gallery): JsonResponse
    {
        if ($gallery->type === 'private') {
            return response()->json([
                'message' => 'Cette galerie est privée.',
            ], 403);
        }

        $gallery->load('photos');

        return response()->json([
            'gallery' => $gallery,
        ]);
    }

    public function showByToken(string $token): JsonResponse
    {
        $gallery = Gallery::where('access_token', $token)
            ->active()
            ->first();

        if (!$gallery || !$gallery->isAccessible($token)) {
            return response()->json([
                'message' => 'Galerie non trouvée ou expirée.',
            ], 404);
        }

        $gallery->load('photos');

        return response()->json([
            'gallery' => $gallery,
        ]);
    }

    public function myGalleries(Request $request): JsonResponse
    {
        $galleries = Gallery::where('user_id', $request->user()->id)
            ->with('photos')
            ->latest()
            ->get();

        return response()->json([
            'galleries' => $galleries,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_id' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'is_active' => ['boolean'],
        ]);

        // All galleries have both share_code and access_token
        $validated['type'] = 'private';
        $validated['access_token'] = Str::random(64);
        $validated['share_code'] = Gallery::generateUniqueShareCode();

        // Map client_id to user_id (only if not empty)
        if (!empty($validated['client_id'])) {
            $validated['user_id'] = $validated['client_id'];
        }
        unset($validated['client_id']);

        // Map expires_at to expiration_at (only if not empty)
        if (!empty($validated['expires_at'])) {
            $validated['expiration_at'] = $validated['expires_at'];
        }
        unset($validated['expires_at']);

        // Default is_active to true
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $gallery = Gallery::create($validated);

        return response()->json([
            'success' => true,
            'data' => $gallery,
            'message' => 'Galerie créée avec succès.',
        ], 201);
    }

    public function update(Request $request, Gallery $gallery): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_id' => ['nullable', 'exists:users,id'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        // Map client_id to user_id
        if (array_key_exists('client_id', $validated)) {
            $validated['user_id'] = $validated['client_id'] ?: null;
            unset($validated['client_id']);
        }

        // Map expires_at to expiration_at
        if (array_key_exists('expires_at', $validated)) {
            $validated['expiration_at'] = $validated['expires_at'] ?: null;
            unset($validated['expires_at']);
        }

        $gallery->update($validated);

        return response()->json([
            'success' => true,
            'data' => $gallery->fresh(),
            'message' => 'Galerie mise à jour avec succès.',
        ]);
    }

    public function destroy(Gallery $gallery): JsonResponse
    {
        $galleryId = $gallery->id;

        // Delete gallery from database first
        $gallery->delete();

        // Call Supabase edge function to cleanup storage files
        try {
            Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.supabase.service_key'),
            ])->post(config('services.supabase.edge_function_url') . '/cleanup-gallery-files', [
                'gallery_id' => $galleryId,
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the request since DB deletion succeeded
            \Log::warning('Failed to cleanup gallery files from storage', [
                'gallery_id' => $galleryId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Galerie supprimée avec succès.',
        ]);
    }

    public function regenerateToken(Gallery $gallery): JsonResponse
    {
        $gallery->update([
            'access_token' => Str::random(64),
        ]);

        return response()->json([
            'gallery' => $gallery->fresh(),
            'message' => 'Token régénéré avec succès.',
        ]);
    }

    public function downloadPhotos(Gallery $gallery, Request $request): JsonResponse
    {
        if (!$gallery->isAccessible()) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // TODO: Implement zip download logic with Supabase storage
        return response()->json([
            'message' => 'Téléchargement en préparation...',
            'download_url' => null,
        ]);
    }

    public function showByShareCode(string $code): JsonResponse
    {
        $gallery = Gallery::byShareCode($code)
            ->active()
            ->first();

        if (!$gallery) {
            return response()->json([
                'message' => 'Code invalide ou galerie expirée.',
            ], 404);
        }

        if ($gallery->isExpired()) {
            return response()->json([
                'message' => 'Cette galerie a expiré.',
            ], 403);
        }

        // Track view
        $gallery->recordView();

        $gallery->load(['photos' => function ($query) {
            $query->ordered();
        }]);

        return response()->json([
            'gallery' => $gallery,
            'mode' => 'protected',
        ]);
    }

    public function showDownloadableByToken(string $token): JsonResponse
    {
        $gallery = Gallery::byAccessToken($token)
            ->active()
            ->first();

        if (!$gallery) {
            return response()->json([
                'message' => 'Lien invalide ou galerie expirée.',
            ], 404);
        }

        if ($gallery->isExpired()) {
            return response()->json([
                'message' => 'Cette galerie a expiré.',
            ], 403);
        }

        // Track view
        $gallery->recordView();

        $gallery->load(['photos' => function ($query) {
            $query->downloadable()->ordered();
        }]);

        return response()->json([
            'gallery' => $gallery,
            'mode' => 'download',
        ]);
    }

    public function regenerateShareCode(Gallery $gallery): JsonResponse
    {
        $newCode = $gallery->regenerateShareCode();

        return response()->json([
            'gallery' => $gallery->fresh(),
            'share_code' => $newCode,
            'message' => 'Code de partage régénéré avec succès.',
        ]);
    }

    public function downloadZip(Gallery $gallery, Request $request): JsonResponse
    {
        $token = $request->query('token');

        if (!$gallery->isAccessible($token)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $photos = $gallery->photos()->downloadable()->get();

        if ($photos->isEmpty()) {
            return response()->json([
                'message' => 'Aucune photo téléchargeable.',
            ], 404);
        }

        $storageService = new SupabaseStorageService();
        $zipFilename = 'gallery_' . $gallery->id . '_' . time() . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFilename);

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return response()->json([
                'message' => 'Erreur lors de la création du ZIP.',
            ], 500);
        }

        foreach ($photos as $index => $photo) {
            $fileContent = $storageService->downloadPhoto($photo->file_path);
            if ($fileContent) {
                $extension = pathinfo($photo->file_path, PATHINFO_EXTENSION);
                $filename = ($photo->title ?? 'photo_' . ($index + 1)) . '.' . $extension;
                $zip->addFromString($filename, $fileContent);
            }
        }

        $zip->close();

        $downloadUrl = url('/api/galleries/' . $gallery->id . '/download-file?file=' . $zipFilename);

        return response()->json([
            'download_url' => $downloadUrl,
            'filename' => $zipFilename,
            'photos_count' => $photos->count(),
        ]);
    }

    public function downloadFile(Gallery $gallery, Request $request)
    {
        $filename = $request->query('file');
        $filePath = storage_path('app/temp/' . $filename);

        if (!file_exists($filePath)) {
            return response()->json([
                'message' => 'Fichier non trouvé.',
            ], 404);
        }

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function adminIndex(): JsonResponse
    {
        $galleries = Gallery::with(['photos', 'user'])
            ->withCount('photos')
            ->latest()
            ->paginate(20);

        $galleries->getCollection()->transform(function ($gallery) {
            $gallery->total_likes = $gallery->photos->sum('likes_count');
            $gallery->downloadable_count = $gallery->photos->where('is_downloadable', true)->count();
            $gallery->liked_photos_count = $gallery->photos->where('likes_count', '>', 0)->count();
            return $gallery;
        });

        return response()->json($galleries);
    }

    public function adminShow(Gallery $gallery): JsonResponse
    {
        $gallery->load(['photos' => function ($query) {
            $query->ordered();
        }, 'user']);

        $gallery->total_likes = $gallery->photos->sum('likes_count');
        $gallery->downloadable_count = $gallery->photos->where('is_downloadable', true)->count();
        $gallery->liked_photos_count = $gallery->photos->where('likes_count', '>', 0)->count();

        return response()->json([
            'success' => true,
            'data' => $gallery,
        ]);
    }
}
