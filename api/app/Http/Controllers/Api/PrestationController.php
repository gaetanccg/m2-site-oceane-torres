<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrestationRequest;
use App\Http\Requests\Admin\UpdatePrestationRequest;
use App\Http\Resources\PrestationResource;
use App\Models\Prestation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PrestationController extends Controller
{
    public function index(): JsonResponse
    {
        // Cache for 10 minutes - prestations rarely change
        $prestations = Cache::remember('prestations_public', 600, function () {
            return Prestation::active()
                ->orderBy('sort_order')
                ->get();
        });

        return response()->json([
            'prestations' => PrestationResource::collection($prestations),
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        $prestations = Prestation::orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PrestationResource::collection($prestations),
        ]);
    }

    public function show(Prestation $prestation): JsonResponse
    {
        return response()->json([
            'prestation' => new PrestationResource($prestation),
        ]);
    }

    public function store(StorePrestationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $prestation = Prestation::create($validated);

        Cache::forget('prestations_public');

        return response()->json([
            'prestation' => new PrestationResource($prestation),
            'message' => 'Prestation créée avec succès.',
        ], 201);
    }

    public function update(UpdatePrestationRequest $request, Prestation $prestation): JsonResponse
    {
        $validated = $request->validated();

        $prestation->update($validated);

        Cache::forget('prestations_public');

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

        Cache::forget('prestations_public');

        return response()->json([
            'message' => 'Prestation supprimée avec succès.',
        ]);
    }

    public function toggle(Prestation $prestation): JsonResponse
    {
        $prestation->update([
            'is_active' => ! $prestation->is_active,
        ]);

        Cache::forget('prestations_public');

        return response()->json([
            'prestation' => new PrestationResource($prestation->fresh()),
            'message' => 'Statut de la prestation modifié.',
        ]);
    }
}
