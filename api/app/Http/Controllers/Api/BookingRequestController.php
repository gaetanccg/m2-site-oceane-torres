<?php

namespace App\Http\Controllers\Api;

use App\Events\BookingRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Client;
use App\Models\Prestation;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BookingRequestController extends Controller
{
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $prestation = Prestation::find($validated['prestation_id']);
        if (! $prestation || ! $prestation->is_active) {
            return response()->json([
                'message' => 'Cette prestation n\'est plus disponible.',
            ], 422);
        }

        $client = Client::where('email', $validated['email'])->first();

        if (! $client) {
            $client = Client::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'source' => 'reservation',
                'gdpr_consent' => true,
                'gdpr_consent_at' => now(),
            ]);
        } else {
            if (! $client->gdpr_consent) {
                $client->update([
                    'gdpr_consent' => true,
                    'gdpr_consent_at' => now(),
                ]);
            }
            if (! empty($validated['phone']) && empty($client->phone)) {
                $client->update(['phone' => $validated['phone']]);
            }
        }

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
            'date' => null,
        ]);

        Log::info('New booking request received', [
            'reservation_id' => $reservation->id,
            'client_id' => $client->id,
            'prestation' => $prestation->title,
        ]);

        BookingRequested::dispatch($reservation, $prestation, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Votre demande a été envoyée avec succes. Nous vous contacterons tres bientot !',
        ], 201);
    }
}
