<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $pendingReservations = Reservation::pending()
            ->with(['user', 'prestation'])
            ->latest()
            ->take(5)
            ->get();

        $upcomingReservations = Reservation::confirmed()
            ->upcoming()
            ->with(['user', 'prestation'])
            ->orderBy('date')
            ->take(5)
            ->get();

        $recentPayments = Payment::completed()
            ->with('user')
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
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Monthly revenue
        $monthlyRevenue = Payment::completed()
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->sum('amount');

        // Total revenue
        $totalRevenue = Payment::completed()->sum('amount');

        // Reservations stats
        $totalReservations = Reservation::count();
        $pendingReservations = Reservation::pending()->count();
        $confirmedReservations = Reservation::confirmed()->count();

        // Users stats
        $totalClients = User::where('role', 'client')->count();
        $newClientsThisMonth = User::where('role', 'client')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        // Galleries stats
        $totalGalleries = Gallery::count();
        $publicGalleries = Gallery::public()->count();
        $privateGalleries = Gallery::private()->count();

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

        return response()->json([
            'revenue' => [
                'monthly' => $monthlyRevenue,
                'total' => $totalRevenue,
                'by_month' => $revenueByMonth,
            ],
            'reservations' => [
                'total' => $totalReservations,
                'pending' => $pendingReservations,
                'confirmed' => $confirmedReservations,
            ],
            'clients' => [
                'total' => $totalClients,
                'new_this_month' => $newClientsThisMonth,
            ],
            'galleries' => [
                'total' => $totalGalleries,
                'public' => $publicGalleries,
                'private' => $privateGalleries,
            ],
        ]);
    }
}
