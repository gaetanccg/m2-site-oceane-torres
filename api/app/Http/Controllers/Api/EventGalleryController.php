<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetThumbnailRequest;
use App\Http\Requests\Admin\StoreEventGalleryRequest;
use App\Http\Requests\Admin\UpdateEventGalleryRequest;
use App\Http\Resources\GalleryResource;
use App\Models\EventGallery;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\MinioStorageService;
use App\Traits\ClearsEventGalleriesCache;
use App\Traits\SyncsProductTypes;
use Illuminate\Http\JsonResponse;

class EventGalleryController extends Controller
{
    use ClearsEventGalleriesCache;
    use SyncsProductTypes;

    // ==========================================
    // Public
    // ==========================================

    public function index(): JsonResponse
    {
        // Normalises AVANT d'entrer dans la cle de cache : sans cela « ?page=01 » et
        // « ?page=1 » creaient deux entrees pour la meme reponse.
        $page = max(1, request()->integer('page', 1));
        $perPage = min(max(1, request()->integer('per_page', 12) ?: 12), 100);

        $galleries = $this->rememberEventGalleries($page, $perPage, function () use ($perPage) {
            $result = Gallery::where('type', 'event')
                ->published()
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
                ->paginate($perPage);

            $result->getCollection()->transform(function ($gallery) {
                $gallery->cover_photo = $gallery->thumbnailPhoto ?? $gallery->photos->first();
                $gallery->unsetRelation('thumbnailPhoto');

                return $gallery;
            });

            return $result;
        });

        $galleries->setCollection(
            GalleryResource::collection($galleries->getCollection())->collection
        );

        return response()->json($galleries);
    }

    public function show(EventGallery $gallery): JsonResponse
    {
        // La NATURE est garantie par le type de parametre : le route-model-binding cherche
        // dans le perimetre d'EventGallery, donc un identifiant de galerie cliente ou
        // scolaire rend 404 avant d'entrer ici. Reste la publication, qui n'est pas une
        // nature mais un etat.
        if (! $gallery->is_published) {
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
                    $query->published()
                        ->withCount('photos')
                        ->with(['thumbnailPhoto', 'photos' => function ($q) {
                            $q->ordered()->limit(1);
                        }]);
                },
            ]);

            $gallery->children->each(function ($child) {
                $child->cover_photo = $child->thumbnailPhoto ?? $child->photos->first();
                $child->unsetRelation('thumbnailPhoto');
            });

            return response()->json([
                'gallery' => new GalleryResource($gallery),
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
            'gallery' => new GalleryResource($gallery),
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
            ->paginate(min((int) request()->integer('per_page', 20) ?: 20, 100));

        $galleries->getCollection()->transform(function ($gallery) {
            $gallery->cover_photo = $gallery->thumbnailPhoto ?? $gallery->photos->first();
            $gallery->unsetRelation('thumbnailPhoto');

            return $gallery->withPrivateFields();
        });

        return response()->json($galleries);
    }

    public function adminShow(EventGallery $gallery): JsonResponse
    {
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
                $child->unsetRelation('thumbnailPhoto');
            });

            return response()->json([
                'success' => true,
                'data' => $gallery->withPrivateFields(),
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
            'data' => $gallery->withPrivateFields(),
            'is_parent' => false,
        ]);
    }

    public function store(StoreEventGalleryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Validate parent gallery constraints
        if (! empty($validated['parent_id'])) {
            // `EventGallery::find` ne trouve QUE des galeries d'evenement : la nature du
            // parent n'a plus a etre comparee a la main.
            $parent = EventGallery::find($validated['parent_id']);

            if (! $parent) {
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
            'data' => $gallery->load('galleryProductTypes.packTiers')->withPrivateFields(),
            'message' => 'Galerie événement créée avec succès.',
        ], 201);
    }

    public function update(UpdateEventGalleryRequest $request, EventGallery $gallery): JsonResponse
    {
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
            'data' => $gallery->fresh()->load(['galleryProductTypes.packTiers', 'eventCategory'])->withPrivateFields(),
            'message' => 'Galerie événement mise à jour avec succès.',
        ]);
    }

    public function destroy(EventGallery $gallery): JsonResponse
    {
        $galleryId = $gallery->id;
        // Capturer les IDs des sous-galeries AVANT le delete cascade.
        $childIds = $gallery->children->pluck('id')->all();

        // Suppression DB d'abord (rapide). Le cleanup MinIO peut être très lent pour
        // une galerie événement avec plusieurs sous-galeries × N photos chacune
        // (>10 s via Cloudflare tunnel) — on le défère après envoi de la réponse pour
        // éviter le timeout côté client.
        $gallery->delete();
        $this->clearEventGalleriesCache();

        app()->terminating(function () use ($galleryId, $childIds) {
            $storageService = app(MinioStorageService::class);

            foreach ($childIds as $childId) {
                try {
                    $storageService->deleteGalleryFolder($childId);
                } catch (\Exception $e) {
                    \Log::warning('Failed to cleanup child gallery files from storage', [
                        'child_gallery_id' => $childId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $storageService->deleteGalleryFolder($galleryId);
            } catch (\Exception $e) {
                \Log::warning('Failed to cleanup event gallery files from storage', [
                    'gallery_id' => $galleryId,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Galerie événement supprimée avec succès.',
        ]);
    }

    public function setThumbnail(SetThumbnailRequest $request, EventGallery $gallery): JsonResponse
    {
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
            'data' => $gallery->fresh()->load('thumbnailPhoto')->withPrivateFields(),
            'message' => $validated['photo_id']
                ? 'Photo définie comme miniature.'
                : 'Miniature supprimée.',
        ]);
    }

    public function children(EventGallery $gallery): JsonResponse
    {
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
            $child->unsetRelation('thumbnailPhoto');
        });

        return response()->json([
            'success' => true,
            'data' => $children->each->withPrivateFields(),
        ]);
    }
}
