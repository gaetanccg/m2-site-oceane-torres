<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrestationRequest;
use App\Http\Requests\Admin\UpdatePrestationRequest;
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
            'prestations' => $prestations,
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        $prestations = Prestation::orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $prestations,
        ]);
    }

    public function show(Prestation $prestation): JsonResponse
    {
        return response()->json([
            'prestation' => $prestation,
        ]);
    }

    public function store(StorePrestationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $prestation = Prestation::create($validated);

        Cache::forget('prestations_public');

        return response()->json([
            'prestation' => $prestation,
            'message' => 'Prestation créée avec succès.',
        ], 201);
    }

    public function update(UpdatePrestationRequest $request, Prestation $prestation): JsonResponse
    {
        $validated = $request->validated();

        $prestation->update($validated);

        Cache::forget('prestations_public');

        return response()->json([
            'prestation' => $prestation->fresh(),
            'message' => 'Prestation mise à jour avec succès.',
        ]);
    }

    public function destroy(Prestation $prestation): JsonResponse
    {
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
            'prestation' => $prestation->fresh(),
            'message' => 'Statut de la prestation modifié.',
        ]);
    }
}
