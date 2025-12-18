<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reservations = Reservation::where('user_id', $request->user()->id)
            ->with(['prestation', 'clientForm'])
            ->latest()
            ->paginate(10);

        return response()->json($reservations);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $query = Reservation::with(['user', 'prestation', 'clientForm']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $reservations = $query->latest()->paginate(20);

        return response()->json($reservations);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $reservation->load(['user', 'prestation', 'clientForm', 'payments']);

        return response()->json([
            'reservation' => $reservation,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prestation_id' => ['required', 'exists:prestations,id'],
            'date' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string'],
            'client_form' => ['required', 'array'],
            'client_form.fullname' => ['required', 'string', 'max:255'],
            'client_form.phone' => ['nullable', 'string', 'max:20'],
            'client_form.requirements' => ['nullable', 'string'],
            'client_form.message' => ['nullable', 'string'],
        ]);

        $reservation = Reservation::create([
            'user_id' => $request->user()->id,
            'prestation_id' => $validated['prestation_id'],
            'date' => $validated['date'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        $reservation->clientForm()->create($validated['client_form']);

        return response()->json([
            'reservation' => $reservation->load(['prestation', 'clientForm']),
            'message' => 'Réservation créée avec succès.',
        ], 201);
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'date', 'after:now'],
            'notes' => ['nullable', 'string'],
        ]);

        $reservation->update($validated);

        return response()->json([
            'reservation' => $reservation->fresh(),
            'message' => 'Réservation mise à jour avec succès.',
        ]);
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        if ($reservation->status === 'confirmed') {
            return response()->json([
                'message' => 'Impossible de supprimer une réservation confirmée.',
            ], 422);
        }

        $reservation->delete();

        return response()->json([
            'message' => 'Réservation supprimée avec succès.',
        ]);
    }

    public function updateStatus(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,cancelled,completed'],
        ]);

        $reservation->update($validated);

        return response()->json([
            'reservation' => $reservation->fresh(),
            'message' => 'Statut mis à jour avec succès.',
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $reservations = Reservation::with('prestation')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy(fn ($r) => $r->date->format('Y-m-d'));

        return response()->json([
            'reservations' => $reservations,
        ]);
    }
}
