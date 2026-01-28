<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Liste paginée des clients avec recherche
     */
    public function index(Request $request): JsonResponse
    {
        $query = Client::query();

        // Recherche par nom ou email
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Filtrer par source
        if ($request->has('source') && $request->source) {
            $query->where('source', $request->source);
        }

        $clients = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json($clients);
    }

    /**
     * Détail d'un client avec ses réservations
     */
    public function show(Client $client): JsonResponse
    {
        $client->load(['reservations.prestation', 'reservations.payments', 'user']);

        return response()->json([
            'client' => $client,
        ]);
    }

    /**
     * Création manuelle d'un client
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:clients'],
            'phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'gdpr_consent' => ['sometimes', 'boolean'],
        ]);

        $client = Client::create([
            ...$validated,
            'source' => 'manual',
            'gdpr_consent' => $validated['gdpr_consent'] ?? false,
            'gdpr_consent_at' => ($validated['gdpr_consent'] ?? false) ? now() : null,
        ]);

        return response()->json([
            'client' => $client,
            'message' => 'Client créé avec succès.',
        ], 201);
    }

    /**
     * Modification d'un client
     */
    public function update(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:clients,email,'.$client->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'gdpr_consent' => ['sometimes', 'boolean'],
        ]);

        // Si le consentement RGPD passe de false à true, enregistrer la date
        if (isset($validated['gdpr_consent']) && $validated['gdpr_consent'] && ! $client->gdpr_consent) {
            $validated['gdpr_consent_at'] = now();
        }

        $client->update($validated);

        return response()->json([
            'client' => $client->fresh(),
            'message' => 'Client mis à jour.',
        ]);
    }

    /**
     * Suppression d'un client (RGPD - droit à l'oubli)
     */
    public function destroy(Client $client): JsonResponse
    {
        // Délier les réservations (on garde les réservations mais on les anonymise)
        $client->reservations()->update([
            'client_id' => null,
            'guest_name' => 'Client supprimé',
            'guest_email' => null,
            'guest_phone' => null,
        ]);

        $client->delete();

        return response()->json([
            'message' => 'Client supprimé conformément au RGPD.',
        ]);
    }

    /**
     * Historique des réservations d'un client
     */
    public function reservations(Client $client): JsonResponse
    {
        $reservations = $client->reservations()
            ->with(['prestation', 'payments'])
            ->latest()
            ->paginate(20);

        return response()->json($reservations);
    }

    /**
     * Export RGPD des données d'un client
     */
    public function gdprExport(Client $client): JsonResponse
    {
        $exportData = $client->gdprExport();

        return response()->json([
            'data' => $exportData,
            'message' => 'Export RGPD généré avec succès.',
        ]);
    }
}
