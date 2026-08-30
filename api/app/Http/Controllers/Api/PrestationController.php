<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrestationResource;
use App\Models\Prestation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Les prestations, cote public : le catalogue que voit une visiteuse.
 *
 * L'administration est dans `Api\Admin\PrestationController`. Ce qui les separe n'est pas le
 * confort de lecture mais l'audience : ce controleur-ci ne doit jamais rien ecrire.
 */
class PrestationController extends Controller
{
    public function index(): JsonResponse
    {
        // Le catalogue change rarement. `Prestation::booted()` invalide cette cle des qu'une
        // prestation bouge — l'invalidation n'est plus a la charge des ecrivains.
        $prestations = Cache::remember(Prestation::CACHE_PUBLIC, 600, function () {
            return Prestation::active()
                ->orderBy('sort_order')
                ->get();
        });

        return response()->json([
            'prestations' => PrestationResource::collection($prestations),
        ]);
    }

    public function show(Prestation $prestation): JsonResponse
    {
        return response()->json([
            'prestation' => new PrestationResource($prestation),
        ]);
    }
}
