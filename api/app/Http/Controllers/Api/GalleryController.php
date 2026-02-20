<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GalleryAccessMail;
use App\Models\CartItem;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\GalleryProductType;
use App\Models\Photo;
use App\Services\MinioStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use ZipArchive;

class GalleryController extends Controller
{
    public function index(): JsonResponse
    {
        $galleries = Gallery::public()
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
        $gallery = Gallery::where('access_token', $token)->first();

        if (! $gallery || ! $gallery->isAccessible($token)) {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $gallery->load('photos');

        return response()->json([
            'gallery' => $gallery,
        ]);
    }

    public function myGalleries(Request $request): JsonResponse
    {
        $user = $request->user();

        $galleries = Gallery::where('user_id', $user->id)
            ->orWhere('assigned_email', $user->email)
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
            'client_id' => ['nullable', 'exists:clients,id'],
            'assigned_email' => ['nullable', 'email', 'max:255'],
            'product_types' => ['nullable', 'array'],
            'product_types.*.product_type' => ['required_with:product_types', 'string', 'in:digital,print_10x15,print_15x20'],
            'product_types.*.is_enabled' => ['required_with:product_types', 'boolean'],
            'product_types.*.price' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $productTypes = $validated['product_types'] ?? null;
        unset($validated['product_types']);

        $validated['type'] = 'private';
        $validated['access_token'] = Str::random(64);
        $validated['share_code'] = Gallery::generateUniqueShareCode();

        // Gerer le choix : soit client_id, soit assigned_email
        if (! empty($validated['client_id'])) {
            // Recuperer le user_id du client
            $client = Client::find($validated['client_id']);
            $validated['user_id'] = $client?->user_id;
            $validated['assigned_email'] = null;
        }
        unset($validated['client_id']);

        $gallery = Gallery::create($validated);

        if ($productTypes !== null) {
            $this->syncProductTypes($gallery, $productTypes);
        }

        return response()->json([
            'success' => true,
            'data' => $gallery->load('galleryProductTypes'),
            'message' => 'Galerie créée avec succès.',
        ], 201);
    }

    public function update(Request $request, Gallery $gallery): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'assigned_email' => ['nullable', 'email', 'max:255'],
            'product_types' => ['nullable', 'array'],
            'product_types.*.product_type' => ['required_with:product_types', 'string', 'in:digital,print_10x15,print_15x20'],
            'product_types.*.is_enabled' => ['required_with:product_types', 'boolean'],
            'product_types.*.price' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $productTypes = $validated['product_types'] ?? null;
        unset($validated['product_types']);

        // Gerer le choix : soit client_id, soit assigned_email
        if (array_key_exists('client_id', $validated)) {
            if (! empty($validated['client_id'])) {
                // Recuperer le user_id du client
                $client = Client::find($validated['client_id']);
                $validated['user_id'] = $client?->user_id;
                $validated['assigned_email'] = null;
            } else {
                $validated['user_id'] = null;
            }
            unset($validated['client_id']);
        }

        // Si on assigne par email, on retire l'user_id
        if (! empty($validated['assigned_email'])) {
            $validated['user_id'] = null;
        }

        $gallery->update($validated);

        if ($productTypes !== null) {
            $this->syncProductTypes($gallery, $productTypes);
        }

        return response()->json([
            'success' => true,
            'data' => $gallery->fresh()->load('galleryProductTypes'),
            'message' => 'Galerie mise à jour avec succès.',
        ]);
    }

    public function destroy(Gallery $gallery): JsonResponse
    {
        $galleryId = $gallery->id;

        try {
            $storageService = new MinioStorageService;
            $storageService->deleteGalleryFolder($galleryId);
        } catch (\Exception $e) {
            \Log::warning('Failed to cleanup gallery files from storage', [
                'gallery_id' => $galleryId,
                'error' => $e->getMessage(),
            ]);
        }

        $gallery->delete();

        return response()->json([
            'message' => 'Galerie supprimée avec succès.',
        ]);
    }

    public function sendAccessEmail(Request $request, Gallery $gallery): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'recipient_name' => ['required', 'string', 'max:255'],
        ]);

        if (! $gallery->share_code) {
            return response()->json([
                'success' => false,
                'message' => 'Cette galerie n\'a pas de code de partage.',
            ], 400);
        }

        $galleryUrl = config('app.frontend_url', 'https://oceanetorresphotographie.fr').'/gallery';

        try {
            Mail::to($validated['email'])->send(new GalleryAccessMail(
                gallery: $gallery,
                recipientName: $validated['recipient_name'],
                galleryUrl: $galleryUrl,
                shareCode: $gallery->share_code,
            ));

            return response()->json([
                'success' => true,
                'message' => 'Email envoyé avec succès à '.$validated['email'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send gallery access email', [
                'gallery_id' => $gallery->id,
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email: '.$e->getMessage(),
            ], 500);
        }
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

    public function showByShareCode(string $code): JsonResponse
    {
        $gallery = Gallery::byShareCode($code)->first();

        if (! $gallery) {
            return response()->json([
                'message' => 'Code invalide.',
            ], 404);
        }

        $gallery->recordView();

        $gallery->load(['photos' => function ($query) {
            $query->ordered();
        }, 'galleryProductTypes']);

        return response()->json([
            'gallery' => $gallery,
            'mode' => 'protected',
            'available_product_types' => $gallery->getAvailableProductTypes(),
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

        if (! $gallery->isAccessible($token)) {
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

        $storageService = new MinioStorageService;
        $zipFilename = 'gallery_'.$gallery->id.'_'.time().'.zip';
        $zipPath = storage_path('app/temp/'.$zipFilename);

        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return response()->json([
                'message' => 'Erreur lors de la création du ZIP.',
            ], 500);
        }

        foreach ($photos as $index => $photo) {
            $storagePath = $photo->file_path_hd ?? $photo->metadata['storage_path'] ?? $photo->metadata['supabase_path'] ?? $photo->file_path;
            $fileContent = $storageService->getFileContent($storagePath);
            if ($fileContent) {
                $extension = pathinfo($photo->file_path, PATHINFO_EXTENSION);
                $filename = ($photo->title ?? 'photo_'.($index + 1)).'.'.$extension;
                $zip->addFromString($filename, $fileContent);

                // Track the download
                $photo->recordDownload(
                    $request->ip(),
                    $request->userAgent()
                );
            }
        }

        $zip->close();

        $downloadUrl = secure_url('/api/galleries/'.$gallery->id.'/download-file?file='.$zipFilename);

        return response()->json([
            'download_url' => $downloadUrl,
            'filename' => $zipFilename,
            'photos_count' => $photos->count(),
        ]);
    }

    public function downloadFile(Gallery $gallery, Request $request)
    {
        $filename = $request->query('file');
        $filePath = storage_path('app/temp/'.$filename);

        if (! file_exists($filePath)) {
            return response()->json([
                'message' => 'Fichier non trouvé.',
            ], 404);
        }

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function adminIndex(): JsonResponse
    {
        // Optimized query: use withCount instead of loading all photos
        // Use 'true'/'false' strings for PostgreSQL boolean compatibility with EMULATE_PREPARES
        $galleries = Gallery::with(['user:id,first_name,last_name,email', 'galleryProductTypes'])
            ->where('type', '!=', 'event')
            ->withCount([
                'photos',
                'photos as downloadable_count' => function ($query) {
                    $query->whereRaw('is_downloadable = true');
                },
                'photos as liked_photos_count' => function ($query) {
                    $query->whereRaw('is_liked = true');
                },
                'photos as downloaded_photos_count' => function ($query) {
                    $query->where('downloads_count', '>', 0);
                },
            ])
            ->withSum('photos', 'downloads_count')
            ->latest()
            ->paginate(20);

        $galleries->getCollection()->transform(function ($gallery) {
            $gallery->total_downloads_count = $gallery->photos_sum_downloads_count ?? 0;
            $gallery->download_status = $gallery->download_status;
            unset($gallery->photos_sum_downloads_count);

            return $gallery;
        });

        return response()->json($galleries);
    }

    public function adminShow(Gallery $gallery): JsonResponse
    {
        $gallery->load(['photos' => function ($query) {
            $query->ordered();
        }, 'user', 'galleryProductTypes']);

        $gallery->downloadable_count = $gallery->photos->where('is_downloadable', true)->count();
        $gallery->liked_photos_count = $gallery->photos->where('is_liked', true)->count();
        $gallery->total_downloads_count = $gallery->photos->sum('downloads_count');
        $gallery->downloaded_photos_count = $gallery->photos->where('downloads_count', '>', 0)->count();
        $gallery->download_status = $gallery->download_status;

        return response()->json([
            'success' => true,
            'data' => $gallery,
        ]);
    }

    // ==========================================
    // Event Galleries (Public)
    // ==========================================

    public function eventIndex(): JsonResponse
    {
        $page = request()->get('page', 1);

        // Cache for 5 minutes per page
        $galleries = Cache::remember("event_galleries_page_{$page}", 300, function () {
            $result = Gallery::where('type', 'event')
                ->with(['photos' => function ($query) {
                    $query->ordered()->limit(6);
                }, 'thumbnailPhoto'])
                ->withCount('photos')
                ->latest()
                ->paginate(12);

            // Add cover_photo to each gallery (thumbnail or first photo)
            $result->getCollection()->transform(function ($gallery) {
                $gallery->cover_photo = $gallery->thumbnailPhoto ?? $gallery->photos->first();

                return $gallery;
            });

            return $result;
        });

        return response()->json($galleries);
    }

    public function eventShow(Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $gallery->recordView();

        $gallery->load(['photos' => function ($query) {
            $query->ordered();
        }, 'galleryProductTypes']);

        return response()->json([
            'gallery' => $gallery,
            'available_product_types' => $gallery->getAvailableProductTypes(),
        ]);
    }

    // ==========================================
    // Event Galleries (Admin)
    // ==========================================

    public function adminEventIndex(): JsonResponse
    {
        $galleries = Gallery::where('type', 'event')
            ->with(['photos', 'thumbnailPhoto', 'galleryProductTypes'])
            ->withCount('photos')
            ->latest()
            ->paginate(20);

        $galleries->getCollection()->transform(function ($gallery) {
            // Use selected thumbnail or fall back to first photo
            $gallery->cover_photo = $gallery->thumbnailPhoto ?? $gallery->photos->first();

            return $gallery;
        });

        return response()->json($galleries);
    }

    public function adminEventShow(Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $gallery->load(['photos' => function ($query) {
            $query->ordered();
        }, 'galleryProductTypes']);

        return response()->json([
            'success' => true,
            'data' => $gallery,
        ]);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_date' => ['nullable', 'date'],
            'event_link' => ['nullable', 'url', 'max:500'],
            'product_types' => ['nullable', 'array'],
            'product_types.*.product_type' => ['required_with:product_types', 'string', 'in:digital,print_10x15,print_15x20'],
            'product_types.*.is_enabled' => ['required_with:product_types', 'boolean'],
            'product_types.*.price' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $productTypes = $validated['product_types'] ?? null;
        unset($validated['product_types']);

        $validated['type'] = 'event';

        $gallery = Gallery::create($validated);

        if ($productTypes !== null) {
            $this->syncProductTypes($gallery, $productTypes);
        }

        $this->clearEventGalleriesCache();

        return response()->json([
            'success' => true,
            'data' => $gallery->load('galleryProductTypes'),
            'message' => 'Galerie événement créée avec succès.',
        ], 201);
    }

    public function updateEvent(Request $request, Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_date' => ['nullable', 'date'],
            'event_link' => ['nullable', 'url', 'max:500'],
            'product_types' => ['nullable', 'array'],
            'product_types.*.product_type' => ['required_with:product_types', 'string', 'in:digital,print_10x15,print_15x20'],
            'product_types.*.is_enabled' => ['required_with:product_types', 'boolean'],
            'product_types.*.price' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $productTypes = $validated['product_types'] ?? null;
        unset($validated['product_types']);

        $gallery->update($validated);

        if ($productTypes !== null) {
            $this->syncProductTypes($gallery, $productTypes);
        }

        $this->clearEventGalleriesCache();

        return response()->json([
            'success' => true,
            'data' => $gallery->fresh()->load('galleryProductTypes'),
            'message' => 'Galerie événement mise à jour avec succès.',
        ]);
    }

    public function destroyEvent(Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $galleryId = $gallery->id;

        try {
            $storageService = new MinioStorageService;
            $storageService->deleteGalleryFolder($galleryId);
        } catch (\Exception $e) {
            \Log::warning('Failed to cleanup event gallery files from storage', [
                'gallery_id' => $galleryId,
                'error' => $e->getMessage(),
            ]);
        }

        $gallery->delete();

        $this->clearEventGalleriesCache();

        return response()->json([
            'message' => 'Galerie événement supprimée avec succès.',
        ]);
    }

    /**
     * Set or remove thumbnail photo for an event gallery
     */
    public function setEventThumbnail(Request $request, Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $validated = $request->validate([
            'photo_id' => ['nullable', 'uuid', 'exists:photos,id'],
        ]);

        // If photo_id is provided, verify it belongs to this gallery
        if (! empty($validated['photo_id'])) {
            $photo = Photo::find($validated['photo_id']);
            if (! $photo || $photo->gallery_id !== $gallery->id) {
                return response()->json([
                    'message' => 'Cette photo n\'appartient pas à cette galerie.',
                ], 422);
            }
        }

        $gallery->update([
            'thumbnail_photo_id' => $validated['photo_id'] ?? null,
        ]);

        $this->clearEventGalleriesCache();

        return response()->json([
            'success' => true,
            'data' => $gallery->fresh()->load('thumbnailPhoto'),
            'message' => $validated['photo_id']
                ? 'Photo définie comme miniature.'
                : 'Miniature supprimée.',
        ]);
    }

    /**
     * Sync product types configuration for a gallery
     */
    private function syncProductTypes(Gallery $gallery, array $productTypes): void
    {
        // Delete existing config and replace
        $gallery->galleryProductTypes()->delete();

        foreach ($productTypes as $config) {
            GalleryProductType::create([
                'gallery_id' => $gallery->id,
                'product_type' => $config['product_type'],
                'is_enabled' => $config['is_enabled'],
                'price' => $config['price'] ?? null,
            ]);
        }
    }

    /**
     * Clear event galleries cache (all pages)
     */
    private function clearEventGalleriesCache(): void
    {
        // Clear first 10 pages of cache
        for ($i = 1; $i <= 10; $i++) {
            Cache::forget("event_galleries_page_{$i}");
        }
    }
}
