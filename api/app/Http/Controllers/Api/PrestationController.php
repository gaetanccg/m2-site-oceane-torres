<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prestation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrestationController extends Controller
{
    public function index(): JsonResponse
    {
        $prestations = Prestation::active()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'prestations' => $prestations,
        ]);
    }

    public function show(Prestation $prestation): JsonResponse
    {
        return response()->json([
            'prestation' => $prestation,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $prestation = Prestation::create($validated);

        return response()->json([
            'prestation' => $prestation,
            'message' => 'Prestation créée avec succès.',
        ], 201);
    }

    public function update(Request $request, Prestation $prestation): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $prestation->update($validated);

        return response()->json([
            'prestation' => $prestation->fresh(),
            'message' => 'Prestation mise à jour avec succès.',
        ]);
    }

    public function destroy(Prestation $prestation): JsonResponse
    {
        $prestation->delete();

        return response()->json([
            'message' => 'Prestation supprimée avec succès.',
        ]);
    }

    public function toggle(Prestation $prestation): JsonResponse
    {
        $prestation->update([
            'is_active' => !$prestation->is_active,
        ]);

        return response()->json([
            'prestation' => $prestation->fresh(),
            'message' => 'Statut de la prestation modifié.',
        ]);
    }
}
