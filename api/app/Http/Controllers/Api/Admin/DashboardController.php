<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\AdminStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const CACHE_TTL = 300;

    public function __construct(private readonly AdminStatsService $stats) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'reservations' => $this->recentReservations(),
            'orders' => $this->recentOrders(),
            'galleries' => $this->recentGalleries(),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json(
            Cache::remember('dashboard_stats', self::CACHE_TTL, fn () => $this->stats->dashboard())
        );
    }

    private function recentReservations(): array
    {
        return Reservation::query()
            ->with(['user:id,first_name,last_name,email', 'prestation:id,title', 'clientForm:id,reservation_id,fullname,phone'])
            ->select(['id', 'user_id', 'prestation_id', 'guest_name', 'guest_email', 'guest_phone', 'date', 'status', 'created_at'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Reservation $r) => [
                'id' => $r->id,
                'client_name' => $r->client_name,
                'prestation' => $r->prestation ? ['title' => $r->prestation->title] : null,
                'status' => $r->status,
                'date' => $r->date?->toDateString(),
                'created_at' => $r->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function recentOrders(): array
    {
        return Order::query()
            ->withCount('items')
            ->select(['id', 'order_number', 'customer_name', 'customer_email', 'status', 'total', 'created_at'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'customer_name' => $o->customer_name,
                'customer_email' => $o->customer_email,
                'status' => $o->status,
                'total' => (float) $o->total,
                'items_count' => (int) $o->items_count,
                'created_at' => $o->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Colonnes choisies une par une : charger le modèle complet ferait signer une URL MinIO
     * par photo de couverture, pour un encart qui n'affiche aucune image.
     */
    private function recentGalleries(): array
    {
        return Gallery::query()
            ->whereNull('school_session_id')
            ->withCount('photos')
            ->select(['id', 'title', 'views_count', 'created_at'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Gallery $g) => [
                'id' => $g->id,
                'title' => $g->title,
                'photos_count' => (int) $g->photos_count,
                'views_count' => (int) $g->views_count,
                'created_at' => $g->created_at->toIso8601String(),
            ])
            ->all();
    }
}
