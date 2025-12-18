<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'file' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,mp4,mov,webm', 'max:102400'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        // TODO: Handle file upload to Supabase storage
        // TODO: Generate web, HD and watermarked versions with Intervention Image

        $photo = $gallery->photos()->create([
            'file_path' => 'temp/path', // Replace with actual path
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_video' => str_starts_with($request->file('file')->getMimeType(), 'video/'),
        ]);

        return response()->json([
            'photo' => $photo,
            'message' => 'Photo ajoutée avec succès.',
        ], 201);
    }

    public function destroy(Photo $photo): JsonResponse
    {
        // TODO: Delete files from Supabase storage
        $photo->delete();

        return response()->json([
            'message' => 'Photo supprimée avec succès.',
        ]);
    }

    public function like(Photo $photo): JsonResponse
    {
        $photo->increment('likes_count');

        return response()->json([
            'likes_count' => $photo->fresh()->likes_count,
        ]);
    }
}
