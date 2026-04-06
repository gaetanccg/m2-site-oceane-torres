<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\Order;
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

        // Galleries: by user_id or assigned_email
        $galleries = Gallery::where('user_id', $user->id)
            ->orWhere('assigned_email', $user->email)
            ->with(['photos' => function ($query) {
                $query->ordered()->limit(4);
            }])
            ->withCount('photos')
            ->latest()
            ->get();

        // Reservations: by user_id, client_id, or guest_email
        $clientId = Client::where('user_id', $user->id)->value('id');

        $reservationsQuery = Reservation::where('user_id', $user->id);
        if ($clientId) {
            $reservationsQuery->orWhere('client_id', $clientId);
        }
        $reservationsQuery->orWhere('guest_email', $user->email);

        $reservations = $reservationsQuery
            ->with('prestation')
            ->latest()
            ->get();

        // Orders: by user_id or guest_email
        $orders = Order::where('user_id', $user->id)
            ->orWhere('guest_email', $user->email)
            ->with('items.photo')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => (float) $order->total,
                    'currency' => $order->currency,
                    'paid_at' => $order->paid_at?->toIso8601String(),
                    'created_at' => $order->created_at->toIso8601String(),
                    'items_count' => $order->items->count(),
                    'has_prints' => $order->hasPrintItems(),
                    'download_token' => $order->metadata['download_token'] ?? null,
                    'items' => $order->items->map(fn ($item) => [
                        'id' => $item->id,
                        'photo_id' => $item->photo_id,
                        'photo_title' => $item->photo?->title ?? $item->photo_title,
                        'product_type' => $item->product_type,
                        'product_type_label' => $item->product_type_label,
                        'is_print' => $item->isPrint(),
                        'price' => (float) $item->price,
                        'is_downloaded' => (bool) $item->is_downloaded,
                        'thumbnail_url' => $item->photo?->thumbnail_url,
                    ]),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'galleries' => $galleries,
                'reservations' => $reservations,
                'orders' => $orders,
            ],
        ]);
    }
}
