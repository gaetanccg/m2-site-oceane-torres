<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetThumbnailRequest;
use App\Http\Requests\Admin\StoreEventGalleryRequest;
use App\Http\Requests\Admin\UpdateEventGalleryRequest;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\MinioStorageService;
use App\Traits\ClearsEventGalleriesCache;
use App\Traits\SyncsProductTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EventGalleryController extends Controller
{
    use ClearsEventGalleriesCache;
    use SyncsProductTypes;

    // ==========================================
    // Public
    // ==========================================

    public function index(): JsonResponse
    {
        $page = request()->get('page', 1);

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

            $result->getCollection()->transform(function ($gallery) {
                $gallery->cover_photo = $gallery->thumbnailPhoto ?? $gallery->photos->first();

                return $gallery;
            });

            return $result;
        });

        return response()->json($galleries);
    }

    public function show(Gallery $gallery): JsonResponse
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
    // Admin
    // ==========================================

    public function adminIndex(): JsonResponse
    {
        $galleries = Gallery::where('type', 'event')
            ->topLevel()
            ->with([
                'thumbnailPhoto',
                'photos' => fn ($q) => $q->ordered()->limit(1),
                'galleryProductTypes.packTiers',
                'eventCategory',
            ])
            ->withCount(['photos', 'children'])
            ->orderBy('sort_order')
            ->latest()
            ->paginate(20);

        $galleries->getCollection()->transform(function ($gallery) {
            $gallery->cover_photo = $gallery->thumbnailPhoto ?? $gallery->photos->first();

            return $gallery;
        });

        return response()->json($galleries);
    }

    public function adminShow(Gallery $gallery): JsonResponse
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

    public function store(StoreEventGalleryRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

    public function update(UpdateEventGalleryRequest $request, Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $validated = $request->validated();

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

    public function destroy(Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $galleryId = $gallery->id;

        try {
            $storageService = app(MinioStorageService::class);

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

    public function setThumbnail(SetThumbnailRequest $request, Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $validated = $request->validated();

        if (! empty($validated['photo_id'])) {
            $photo = Photo::find($validated['photo_id']);
            if (! $photo) {
                return response()->json([
                    'message' => 'Photo non trouvée.',
                ], 422);
            }

            $allowedGalleryIds = [$gallery->id];
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

    public function children(Gallery $gallery): JsonResponse
    {
        if ($gallery->type !== 'event') {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $children = $gallery->children()
            ->with([
                'thumbnailPhoto',
                'photos' => fn ($q) => $q->ordered()->limit(1),
                'galleryProductTypes.packTiers',
                'eventCategory',
            ])
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
}
