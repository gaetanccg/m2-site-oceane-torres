<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Get the authenticated user's dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        // Recuperer les galeries de l'utilisateur (par user_id ou assigned_email)
        $galleries = Gallery::where('user_id', $user->id)
            ->orWhere('assigned_email', $user->email)
            ->with(['photos' => function ($query) {
                $query->ordered()->limit(4);
            }])
            ->withCount('photos')
            ->latest()
            ->get();

        // Recuperer les reservations de l'utilisateur
        // On cherche par user_id ou par client_id si l'utilisateur a un client lie
        $clientId = Client::where('user_id', $user->id)->value('id');

        $reservationsQuery = Reservation::where('user_id', $user->id);

        if ($clientId) {
            $reservationsQuery->orWhere('client_id', $clientId);
        }

        // Inclure aussi les reservations guest avec le meme email
        $reservationsQuery->orWhere('guest_email', $user->email);

        $reservations = $reservationsQuery
            ->with('prestation')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'galleries' => $galleries,
                'reservations' => $reservations,
            ],
        ]);
    }
}
