<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
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
            'gdpr_consent' => ['required', 'accepted'],
        ]);

        // Verifier que la prestation est active
        $prestation = Prestation::find($validated['prestation_id']);
        if (!$prestation || !$prestation->is_active) {
            return response()->json([
                'message' => 'Cette prestation n\'est plus disponible.',
            ], 422);
        }

        // Trouver ou creer le client
        $client = Client::where('email', $validated['email'])->first();

        if (!$client) {
            $client = Client::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'source' => 'reservation',
                'gdpr_consent' => true,
                'gdpr_consent_at' => now(),
            ]);
        } else {
            // Mettre à jour le consentement si pas encore donné
            if (!$client->gdpr_consent) {
                $client->update([
                    'gdpr_consent' => true,
                    'gdpr_consent_at' => now(),
                ]);
            }
            // Mettre à jour le téléphone si fourni
            if (!empty($validated['phone']) && empty($client->phone)) {
                $client->update(['phone' => $validated['phone']]);
            }
        }

        // Creer la reservation en tant que demande (sans user_id, sans date fixe)
        $reservation = Reservation::create([
            'user_id' => null,
            'client_id' => $client->id,
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
            'client_id' => $client->id,
            'guest_name' => $reservation->guest_name,
            'guest_email' => $reservation->guest_email,
            'prestation' => $prestation->title,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre demande a été envoyée avec succes. Nous vous contacterons tres bientot !',
        ], 201);
    }
}
