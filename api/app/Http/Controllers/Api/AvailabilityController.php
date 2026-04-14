<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateAvailabilitySlotsRequest;
use App\Http\Requests\Admin\StoreAvailabilityPatternRequest;
use App\Http\Requests\Admin\StoreAvailabilitySlotRequest;
use App\Http\Requests\Admin\UpdateAvailabilityPatternRequest;
use App\Http\Requests\Admin\UpdateAvailabilitySlotRequest;
use App\Models\AvailabilityPattern;
use App\Models\AvailabilitySlot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    // ==========================================
    // Slots
    // ==========================================

    public function indexSlots(Request $request): JsonResponse
    {
        $query = AvailabilitySlot::query();

        if ($request->has('date')) {
            $query->forDate($request->date);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('date', $request->month)
                ->whereYear('date', $request->year);
        }

        if ($request->has('from') && $request->has('to')) {
            $query->betweenDates($request->from, $request->to);
        }

        $slots = $query->with('reservation.prestation')
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate(50);

        return response()->json($slots);
    }

    public function availableSlots(Request $request): JsonResponse
    {
        $query = AvailabilitySlot::available()->upcoming();

        if ($request->has('from') && $request->has('to')) {
            $query->betweenDates($request->from, $request->to);
        }

        $slots = $query->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'slots' => $slots,
        ]);
    }

    public function storeSlot(StoreAvailabilitySlotRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Calculer la duree si non fournie
        if (empty($validated['duration_minutes'])) {
            $start = Carbon::parse($validated['start_time']);
            $end = Carbon::parse($validated['end_time']);
            $validated['duration_minutes'] = $start->diffInMinutes($end);
        }

        $validated['status'] = 'available';

        $slot = AvailabilitySlot::create($validated);

        return response()->json([
            'success' => true,
            'data' => $slot,
            'message' => 'Creneau crée avec succes.',
        ], 201);
    }

    public function updateSlot(UpdateAvailabilitySlotRequest $request, AvailabilitySlot $slot): JsonResponse
    {
        if ($slot->isBooked()) {
            return response()->json([
                'message' => 'Impossible de modifier un creneau reserve.',
            ], 422);
        }

        $validated = $request->validated();

        $slot->update($validated);

        return response()->json([
            'success' => true,
            'data' => $slot->fresh(),
            'message' => 'Creneau mis a jour avec succes.',
        ]);
    }

    public function destroySlot(AvailabilitySlot $slot): JsonResponse
    {
        if ($slot->isBooked()) {
            return response()->json([
                'message' => 'Impossible de supprimer un creneau reserve.',
            ], 422);
        }

        $slot->delete();

        return response()->json([
            'message' => 'Creneau supprime avec succes.',
        ]);
    }

    // ==========================================
    // Patterns
    // ==========================================

    public function indexPatterns(): JsonResponse
    {
        $patterns = AvailabilityPattern::latest()->get();

        return response()->json([
            'patterns' => $patterns,
        ]);
    }

    public function storePattern(StoreAvailabilityPatternRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $pattern = AvailabilityPattern::create($validated);

        return response()->json([
            'success' => true,
            'data' => $pattern,
            'message' => 'Modele de disponibilite crée avec succes.',
        ], 201);
    }

    public function updatePattern(UpdateAvailabilityPatternRequest $request, AvailabilityPattern $pattern): JsonResponse
    {
        $validated = $request->validated();

        $pattern->update($validated);

        return response()->json([
            'success' => true,
            'data' => $pattern->fresh(),
            'message' => 'Modele mis a jour avec succes.',
        ]);
    }

    public function destroyPattern(AvailabilityPattern $pattern): JsonResponse
    {
        $pattern->delete();

        return response()->json([
            'message' => 'Modele supprime avec succes.',
        ]);
    }

    public function generateSlots(GenerateAvailabilitySlotsRequest $request, AvailabilityPattern $pattern): JsonResponse
    {
        $validated = $request->validated();

        $untilDate = Carbon::parse($validated['until_date']);
        $slots = $pattern->generateSlots($untilDate);

        return response()->json([
            'success' => true,
            'slots_created' => count($slots),
            'message' => count($slots).' creneaux generes avec succes.',
        ]);
    }

    public function togglePattern(AvailabilityPattern $pattern): JsonResponse
    {
        $pattern->update([
            'is_active' => ! $pattern->is_active,
        ]);

        return response()->json([
            'success' => true,
            'data' => $pattern->fresh(),
            'message' => $pattern->is_active ? 'Modele active.' : 'Modele desactive.',
        ]);
    }
}
