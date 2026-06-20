<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Liste des notifications de l'utilisateur connecte
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title ?? $this->getTitleFromType($notification->type),
                    'message' => $notification->message,
                    'is_read' => $notification->read_at !== null,
                    'data' => $notification->data,
                    'created_at' => $notification->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        // Verifier que la notification appartient a l'utilisateur
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvee.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquee comme lue.',
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues.',
        ]);
    }

    /**
     * Generer un titre a partir du type de notification
     */
    private function getTitleFromType(string $type): string
    {
        return match ($type) {
            'new_reservation' => 'Nouvelle demande de reservation',
            'reservation_confirmed' => 'Reservation confirmee',
            'reservation_cancelled' => 'Reservation annulee',
            'new_contact' => 'Nouveau message de contact',
            'new_payment' => 'Nouveau paiement recu',
            'gallery_viewed' => 'Galerie consultee',
            default => 'Notification',
        };
    }
}
