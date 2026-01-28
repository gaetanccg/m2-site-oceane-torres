<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        // Use select to only fetch needed columns
        $pendingReservations = Reservation::pending()
            ->with([
                'user:id,first_name,last_name,email',
                'prestation:id,title,price',
            ])
            ->select(['id', 'user_id', 'prestation_id', 'guest_name', 'guest_email', 'date', 'status', 'created_at'])
            ->latest()
            ->take(5)
            ->get();

        $upcomingReservations = Reservation::confirmed()
            ->upcoming()
            ->with([
                'user:id,first_name,last_name,email',
                'prestation:id,title,price',
            ])
            ->select(['id', 'user_id', 'prestation_id', 'guest_name', 'guest_email', 'date', 'status', 'created_at'])
            ->orderBy('date')
            ->take(5)
            ->get();

        $recentPayments = Payment::completed()
            ->with('user:id,first_name,last_name,email')
            ->select(['id', 'user_id', 'amount', 'status', 'created_at'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'pending_reservations' => $pendingReservations,
            'upcoming_reservations' => $upcomingReservations,
            'recent_payments' => $recentPayments,
        ]);
    }

    public function stats(): JsonResponse
    {
        // Cache stats for 5 minutes to reduce database load
        $stats = Cache::remember('dashboard_stats', 300, function () {
            return $this->computeStats();
        });

        return response()->json($stats);
    }

    private function computeStats(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $startOfMonth = now()->startOfMonth();

        // Single query for reservation stats using conditional aggregation
        $reservationStats = Reservation::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending")
            ->selectRaw("COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed")
            ->first();

        // Single query for payment stats
        $paymentStats = Payment::completed()
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN amount ELSE 0 END), 0) as monthly', [$startOfMonth])
            ->first();

        // Single query for client stats
        $clientStats = User::where('role', 'client')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(CASE WHEN created_at >= ? THEN 1 END) as new_this_month', [$startOfMonth])
            ->first();

        // Single query for gallery stats using conditional aggregation
        $galleryStats = Gallery::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(CASE WHEN type = 'public' THEN 1 END) as public")
            ->selectRaw("COUNT(CASE WHEN type = 'private' THEN 1 END) as private")
            ->first();

        // Revenue by month (last 12 months)
        $revenueByMonth = Payment::completed()
            ->where('created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw('EXTRACT(YEAR FROM created_at) as year'),
                DB::raw('EXTRACT(MONTH FROM created_at) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return [
            'revenue' => [
                'monthly' => (float) $paymentStats->monthly,
                'total' => (float) $paymentStats->total,
                'by_month' => $revenueByMonth,
            ],
            'reservations' => [
                'total' => (int) $reservationStats->total,
                'pending' => (int) $reservationStats->pending,
                'confirmed' => (int) $reservationStats->confirmed,
            ],
            'clients' => [
                'total' => (int) $clientStats->total,
                'new_this_month' => (int) $clientStats->new_this_month,
            ],
            'galleries' => [
                'total' => (int) $galleryStats->total,
                'public' => (int) $galleryStats->public,
                'private' => (int) $galleryStats->private,
            ],
        ];
    }
}
