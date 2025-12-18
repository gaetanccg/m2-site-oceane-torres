<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'type' => ['required', 'in:public,private'],
            'user_id' => ['nullable', 'exists:users,id'],
            'expiration_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($validated['type'] === 'private') {
            $validated['access_token'] = Str::random(64);
        }

        $gallery = Gallery::create($validated);

        return response()->json([
            'gallery' => $gallery,
            'message' => 'Galerie créée avec succès.',
        ], 201);
    }

    public function update(Request $request, Gallery $gallery): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:public,private'],
            'user_id' => ['nullable', 'exists:users,id'],
            'expiration_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $gallery->update($validated);

        return response()->json([
            'gallery' => $gallery->fresh(),
            'message' => 'Galerie mise à jour avec succès.',
        ]);
    }

    public function destroy(Gallery $gallery): JsonResponse
    {
        $gallery->delete();

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
}
