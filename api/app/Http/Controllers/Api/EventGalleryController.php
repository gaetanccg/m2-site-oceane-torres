<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Models\EventGallery;
use App\Models\Gallery;
use App\Traits\ClearsEventGalleriesCache;
use Illuminate\Http\JsonResponse;

/**
 * Les galeries d'evenement, cote public : la liste et la consultation.
 *
 * L'administration est dans `Api\Admin\EventGalleryController`. Ce controleur-ci ne fait que
 * LIRE — il garde donc le trait pour consulter le cache, pas pour l'invalider.
 */
class EventGalleryController extends Controller
{
    use ClearsEventGalleriesCache;

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

}
