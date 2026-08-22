<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateReservationRequest;
use App\Http\Requests\Admin\UpdateReservationStatusRequest;
use App\Http\Resources\CalendarEventResource;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function adminIndex(Request $request): JsonResponse
    {
        // Charger les relations necessaires pour les accesseurs
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

        // Les attributs client_name, client_email, client_phone, is_guest
        // sont automatiquement inclus grace a $appends dans le modele
        $reservations = $query->latest()->paginate(20);

        return response()->json($reservations);
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

    /**
     * Mise a jour admin d'une reservation (date, heure, statut, notes)
     */
    public function adminUpdate(AdminUpdateReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validated();

        $updateData = [];

        // Combiner date et heure si fournies
        if (isset($validated['date'])) {
            $date = $validated['date'];
            if (isset($validated['time']) && $validated['time']) {
                $date = $date.' '.$validated['time'];
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
            'message' => 'Reservation supprimée avec succes.',
        ]);
    }

    public function updateStatus(UpdateReservationStatusRequest $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validated();

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

        return response()->json([
            'success' => true,
            'data' => CalendarEventResource::collection($reservations),
        ]);
    }
}
