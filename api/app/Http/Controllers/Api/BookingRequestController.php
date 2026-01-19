<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Prestation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingRequestController extends Controller
{
    /**
     * Soumettre une demande de reservation (public, sans authentification)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'prestation_id' => ['required', 'exists:prestations,id'],
            'date_preferences' => ['required', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        // Verifier que la prestation est active
        $prestation = Prestation::find($validated['prestation_id']);
        if (!$prestation || !$prestation->is_active) {
            return response()->json([
                'message' => 'Cette prestation n\'est plus disponible.',
            ], 422);
        }

        // Creer la reservation en tant que demande (sans user_id, sans date fixe)
        $reservation = Reservation::create([
            'user_id' => null,
            'prestation_id' => $validated['prestation_id'],
            'guest_name' => $validated['name'],
            'guest_email' => $validated['email'],
            'guest_phone' => $validated['phone'] ?? null,
            'date_preferences' => $validated['date_preferences'],
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
            'date' => null, // Sera definie lors de la confirmation
        ]);

        Log::info('New booking request received', [
            'reservation_id' => $reservation->id,
            'guest_name' => $reservation->guest_name,
            'guest_email' => $reservation->guest_email,
            'prestation' => $prestation->title,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre demande a ete envoyee avec succes. Nous vous contacterons tres bientot !',
        ], 201);
    }
}
