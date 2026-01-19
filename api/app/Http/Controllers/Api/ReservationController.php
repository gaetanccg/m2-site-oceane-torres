<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\AvailabilitySlot;
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
        // Charger les relations necessaires pour les accesseurs
        $query = Reservation::with(['user', 'prestation', 'clientForm', 'availabilitySlot']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Les attributs client_name, client_email, client_phone, is_guest
        // sont automatiquement inclus grace a $appends dans le modele
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

    /**
     * Afficher une reservation pour l'admin avec tous les details
     */
    public function adminShow(Reservation $reservation): JsonResponse
    {
        // Charger les relations necessaires pour les accesseurs
        $reservation->load(['user', 'prestation', 'clientForm']);

        // Les attributs client_name, client_email, client_phone, is_guest
        // sont automatiquement inclus grace a $appends dans le modele
        return response()->json([
            'success' => true,
            'data' => $reservation,
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

    /**
     * Mise a jour admin d'une reservation (date, heure, statut, notes)
     */
    public function adminUpdate(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:pending,confirmed,cancelled,completed'],
            'notes' => ['nullable', 'string'],
        ]);

        $updateData = [];

        // Combiner date et heure si fournies
        if (isset($validated['date'])) {
            $date = $validated['date'];
            if (isset($validated['time']) && $validated['time']) {
                $date = $date . ' ' . $validated['time'];
            }
            $updateData['date'] = $date;
        }

        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }

        if (array_key_exists('notes', $validated)) {
            $updateData['notes'] = $validated['notes'];
        }

        $reservation->update($updateData);

        // Recharger avec les relations necessaires pour les accesseurs
        $reservation = $reservation->fresh()->load(['user', 'prestation', 'clientForm']);

        return response()->json([
            'success' => true,
            'data' => $reservation,
            'message' => 'Reservation mise a jour avec succes.',
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

    /**
     * Suppression admin d'une reservation (peut supprimer n'importe quel statut)
     */
    public function adminDestroy(Reservation $reservation): JsonResponse
    {
        // Supprimer le clientForm associe si existant
        if ($reservation->clientForm) {
            $reservation->clientForm->delete();
        }

        $reservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reservation supprimee avec succes.',
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
        $start = $request->get('start');
        $end = $request->get('end');

        $query = Reservation::with(['prestation', 'user', 'clientForm'])
            ->whereNotNull('date');

        if ($start) {
            $query->where('date', '>=', $start);
        }
        if ($end) {
            $query->where('date', '<=', $end);
        }

        $reservations = $query->get();

        // Transformer en format CalendarEvent
        $events = $reservations->map(function ($reservation) {
            // Determiner le nom du client
            $clientName = $reservation->client_name ?? 'Client';

            // Calculer la fin (date + duree prestation ou 1h par defaut)
            $startDate = $reservation->date;
            $duration = $reservation->prestation?->duration ?? 60;
            $endDate = $startDate->copy()->addMinutes($duration);

            return [
                'id' => $reservation->id,
                'title' => $reservation->prestation?->title ?? 'Reservation',
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
                'status' => $reservation->status,
                'client' => $clientName,
                'prestation' => $reservation->prestation?->title ?? 'N/A',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Confirmer une reservation en lui assignant un creneau
     */
    public function confirmWithSlot(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validate([
            'slot_id' => ['required', 'exists:availability_slots,id'],
        ]);

        $slot = AvailabilitySlot::findOrFail($validated['slot_id']);

        // Verifier que le creneau est disponible
        if (!$slot->isAvailable()) {
            return response()->json([
                'message' => 'Ce creneau n\'est plus disponible.',
            ], 422);
        }

        // Marquer le creneau comme reserve
        $slot->markAsBooked($reservation);

        // Mettre a jour la reservation
        $reservation->update([
            'status' => 'confirmed',
            'availability_slot_id' => $slot->id,
            'date' => $slot->date,
        ]);

        return response()->json([
            'success' => true,
            'reservation' => $reservation->fresh()->load(['prestation', 'availabilitySlot']),
            'message' => 'Reservation confirmee avec succes.',
        ]);
    }

    /**
     * Annuler une reservation et liberer le creneau
     */
    public function cancelWithSlotRelease(Reservation $reservation): JsonResponse
    {
        // Liberer le creneau si existant
        if ($reservation->availabilitySlot) {
            $reservation->availabilitySlot->release();
        }

        $reservation->update([
            'status' => 'cancelled',
            'availability_slot_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'reservation' => $reservation->fresh(),
            'message' => 'Reservation annulee et creneau libere.',
        ]);
    }
}
