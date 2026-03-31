<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GalleryAccessMail;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\GalleryProductType;
use App\Models\PackTier;
use App\Models\Photo;
use App\Services\MinioStorageService;
use App\Traits\ClearsEventGalleriesCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use ZipArchive;

class GalleryController extends Controller
{
    use ClearsEventGalleriesCache;

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

        $gallery->load(['photos', 'galleryProductTypes.packTiers']);

        return response()->json([
            'gallery' => $gallery,
            'available_product_types' => $gallery->getAvailableProductTypes(),
            'pack_pricing' => $gallery->getPackPricing(),
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
            'product_types.*.tiers' => ['nullable', 'array', 'max:3'],
            'product_types.*.tiers.*.min_quantity' => ['required', 'integer', 'min:2'],
            'product_types.*.tiers.*.unit_price' => ['required', 'numeric', 'min:0.01'],
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
            'data' => $gallery->load('galleryProductTypes.packTiers'),
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
            'product_types.*.tiers' => ['nullable', 'array', 'max:3'],
            'product_types.*.tiers.*.min_quantity' => ['required', 'integer', 'min:2'],
            'product_types.*.tiers.*.unit_price' => ['required', 'numeric', 'min:0.01'],
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
            'data' => $gallery->fresh()->load('galleryProductTypes.packTiers'),
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
            Mail::to($validated['email'])->send(
                new GalleryAccessMail(
                    gallery: $gallery,
                    recipientName: $validated['recipient_name'],
                    galleryUrl: $galleryUrl,
                    shareCode: $gallery->share_code,
                )
            );

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

        $gallery->load([
            'photos' => function ($query) {
                $query->ordered();
            },
            'galleryProductTypes',
        ]);

        return response()->json([
            'gallery' => $gallery,
            'mode' => 'protected',
            'available_product_types' => $gallery->getAvailableProductTypes(),
            'pack_pricing' => $gallery->getPackPricing(),
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

        $downloadUrl = url('/api/galleries/'.$gallery->id.'/download-file?file='.$zipFilename);

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
        $galleries = Gallery::with(['user:id,first_name,last_name,email', 'galleryProductTypes.packTiers'])
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
        $gallery->load([
            'photos' => function ($query) {
                $query->ordered();
            },
            'user',
            'galleryProductTypes.packTiers',
        ]);

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
                ->topLevel()
                ->with([
                    'photos' => function ($query) {
                        $query->ordered()->limit(6);
                    },
                    'thumbnailPhoto',
                    'eventCategory',
                ])
                ->withCount(['photos', 'children'])
                ->orderBy('sort_order')
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

        $isParent = $gallery->children()->exists();

        if ($isParent) {
            $gallery->load([
                'thumbnailPhoto',
                'children' => function ($query) {
                    $query->withCount('photos')
                        ->with(['thumbnailPhoto', 'photos' => function ($q) {
                            $q->ordered()->limit(1);
                        }]);
                },
            ]);

            // Add cover_photo to each child
            $gallery->children->each(function ($child) {
                $child->cover_photo = $child->thumbnailPhoto ?? $child->photos->first();
            });

            return response()->json([
                'gallery' => $gallery,
                'is_parent' => true,
            ]);
        }

        $gallery->load([
            'photos' => function ($query) {
                $query->ordered();
            },
            'galleryProductTypes',
            'parent',
        ]);

        return response()->json([
            'gallery' => $gallery,
            'is_parent' => false,
            'available_product_types' => $gallery->getAvailableProductTypes(),
            'pack_pricing' => $gallery->getPackPricing(),
        ]);
    }

    // ==========================================
    // Event Galleries (Admin)
    // ==========================================

    public function adminEventIndex(): JsonResponse
    {
        $galleries = Gallery::where('type', 'event')
            ->topLevel()
            ->with(['photos', 'thumbnailPhoto', 'galleryProductTypes.packTiers', 'eventCategory'])
            ->withCount(['photos', 'children'])
            ->orderBy('sort_order')
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

        $isParent = $gallery->children()->exists();

        if ($isParent) {
            $gallery->load([
                'thumbnailPhoto',
                'children' => function ($query) {
                    $query->withCount('photos')
                        ->with(['thumbnailPhoto', 'photos' => function ($q) {
                            $q->ordered()->limit(1);
                        }, 'galleryProductTypes.packTiers', 'eventCategory']);
                },
                'galleryProductTypes.packTiers',
            ]);

            // Add cover_photo to each child
            $gallery->children->each(function ($child) {
                $child->cover_photo = $child->thumbnailPhoto ?? $child->photos->first();
            });

            return response()->json([
                'success' => true,
                'data' => $gallery,
                'is_parent' => true,
            ]);
        }

        $gallery->load([
            'photos' => function ($query) {
                $query->ordered();
            },
            'galleryProductTypes.packTiers',
            'parent',
        ]);

        return response()->json([
            'success' => true,
            'data' => $gallery,
            'is_parent' => false,
        ]);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_date' => ['nullable', 'date'],
            'event_link' => ['nullable', 'url', 'max:500'],
            'event_category_id' => ['nullable', 'exists:event_categories,id'],
            'parent_id' => ['nullable', 'uuid', 'exists:galleries,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'product_types' => ['nullable', 'array'],
            'product_types.*.product_type' => ['required_with:product_types', 'string', 'in:digital,print_10x15,print_15x20'],
            'product_types.*.is_enabled' => ['required_with:product_types', 'boolean'],
            'product_types.*.price' => ['nullable', 'numeric', 'min:0.01'],
            'product_types.*.tiers' => ['nullable', 'array', 'max:3'],
            'product_types.*.tiers.*.min_quantity' => ['required', 'integer', 'min:2'],
            'product_types.*.tiers.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ]);

        // Validate parent gallery constraints
        if (! empty($validated['parent_id'])) {
            $parent = Gallery::find($validated['parent_id']);

            if (! $parent || $parent->type !== 'event') {
                return response()->json([
                    'message' => 'Le parent doit être une galerie événement.',
                ], 422);
            }

            if ($parent->parent_id !== null) {
                return response()->json([
                    'message' => 'Un seul niveau de hiérarchie est autorisé.',
                ], 422);
            }

            if ($parent->photos()->exists()) {
                return response()->json([
                    'message' => 'Une galerie parent ne peut pas contenir de photos directement.',
                ], 422);
            }

            // Inherit parent defaults if not provided
            if (empty($validated['event_category_id'])) {
                $validated['event_category_id'] = $parent->event_category_id;
            }
            if (empty($validated['event_date'])) {
                $validated['event_date'] = $parent->event_date;
            }
        }

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
            'data' => $gallery->load('galleryProductTypes.packTiers'),
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
            'event_category_id' => ['nullable', 'exists:event_categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'product_types' => ['nullable', 'array'],
            'product_types.*.product_type' => ['required_with:product_types', 'string', 'in:digital,print_10x15,print_15x20'],
            'product_types.*.is_enabled' => ['required_with:product_types', 'boolean'],
            'product_types.*.price' => ['nullable', 'numeric', 'min:0.01'],
            'product_types.*.tiers' => ['nullable', 'array', 'max:3'],
            'product_types.*.tiers.*.min_quantity' => ['required', 'integer', 'min:2'],
            'product_types.*.tiers.*.unit_price' => ['required', 'numeric', 'min:0.01'],
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
            'data' => $gallery->fresh()->load(['galleryProductTypes.packTiers', 'eventCategory']),
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

            // Clean up children storage before cascade delete removes them
            foreach ($gallery->children as $child) {
                try {
                    $storageService->deleteGalleryFolder($child->id);
                } catch (\Exception $e) {
                    \Log::warning('Failed to cleanup child gallery files from storage', [
                        'child_gallery_id' => $child->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

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

        // If photo_id is provided, verify it belongs to this gallery or one of its children
        if (! empty($validated['photo_id'])) {
            $photo = Photo::find($validated['photo_id']);
            if (! $photo) {
                return response()->json([
                    'message' => 'Photo non trouvée.',
                ], 422);
            }

            $allowedGalleryIds = [$gallery->id];
            // For parent galleries, also allow photos from children
            if ($gallery->children()->exists()) {
                $allowedGalleryIds = array_merge(
                    $allowedGalleryIds,
                    $gallery->children()->pluck('id')->toArray()
                );
            }

            if (! in_array($photo->gallery_id, $allowedGalleryIds)) {
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
     * Get children of a parent event gallery (admin)
     */
    public function adminEventChildren(Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $children = $gallery->children()
            ->with(['photos', 'thumbnailPhoto', 'galleryProductTypes.packTiers', 'eventCategory'])
            ->withCount('photos')
            ->get();

        $children->each(function ($child) {
            $child->cover_photo = $child->thumbnailPhoto ?? $child->photos->first();
        });

        return response()->json([
            'success' => true,
            'data' => $children,
        ]);
    }

    /**
     * Sync product types configuration for a gallery
     */
    private function syncProductTypes(Gallery $gallery, array $productTypes): void
    {
        // Delete existing config and replace (cascade deletes pack_tiers)
        $gallery->galleryProductTypes()->delete();

        foreach ($productTypes as $config) {
            $gpt = GalleryProductType::create([
                'gallery_id' => $gallery->id,
                'product_type' => $config['product_type'],
                'is_enabled' => $config['is_enabled'],
                'price' => $config['price'] ?? null,
            ]);

            if (! empty($config['tiers'])) {
                foreach ($config['tiers'] as $tier) {
                    PackTier::create([
                        'gallery_product_type_id' => $gpt->id,
                        'min_quantity' => $tier['min_quantity'],
                        'unit_price' => $tier['unit_price'],
                    ]);
                }
            }
        }
    }
}
