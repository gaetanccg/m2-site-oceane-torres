<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrestationRequest;
use App\Http\Requests\Admin\UpdatePrestationRequest;
use App\Http\Resources\PrestationResource;
use App\Models\Prestation;
use Illuminate\Http\JsonResponse;

/**
 * Les prestations, cote administration.
 *
 * L'invalidation du cache public n'est plus ici : `Prestation::booted()` s'en charge. Elle
 * etait recopiee dans les quatre methodes qui ecrivent — le meme motif qui a laisse trois
 * chemins de photos ne jamais rafraichir la liste des evenements.
 */
class PrestationController extends Controller
{
    public function index(): JsonResponse
    {
        $prestations = Prestation::orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PrestationResource::collection($prestations),
        ]);
    }

    public function store(StorePrestationRequest $request): JsonResponse
    {
        $prestation = Prestation::create($request->validated());

        return response()->json([
            'prestation' => new PrestationResource($prestation),
            'message' => 'Prestation créée avec succès.',
        ], 201);
    }

    public function update(UpdatePrestationRequest $request, Prestation $prestation): JsonResponse
    {
        $prestation->update($request->validated());

        return response()->json([
            'prestation' => new PrestationResource($prestation->fresh()),
            'message' => 'Prestation mise à jour avec succès.',
        ]);
    }

    public function destroy(Prestation $prestation): JsonResponse
    {
        $reservations = $prestation->reservations()->count();

        if ($reservations > 0) {
            throw new BusinessException(
                sprintf(
                    '%d réservation(s) référencent cette prestation. Désactivez-la plutôt que de la supprimer, pour ne pas perdre leur historique.',
                    $reservations
                ),
                409
            );
        }

        $prestation->delete();

        return response()->json([
            'message' => 'Prestation supprimée avec succès.',
        ]);
    }

    public function toggle(Prestation $prestation): JsonResponse
    {
        $prestation->update(['is_active' => ! $prestation->is_active]);

        return response()->json([
            'prestation' => new PrestationResource($prestation->fresh()),
            'message' => 'Statut de la prestation modifié.',
        ]);
    }
}
