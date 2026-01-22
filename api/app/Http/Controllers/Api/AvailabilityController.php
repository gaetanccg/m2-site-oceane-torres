<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvailabilitySlot;
use App\Models\AvailabilityPattern;
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

    public function storeSlot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'notes' => ['nullable', 'string'],
        ]);

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

    public function updateSlot(Request $request, AvailabilitySlot $slot): JsonResponse
    {
        if ($slot->isBooked()) {
            return response()->json([
                'message' => 'Impossible de modifier un creneau reserve.',
            ], 422);
        }

        $validated = $request->validate([
            'date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'status' => ['sometimes', 'in:available,unavailable'],
            'notes' => ['nullable', 'string'],
        ]);

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

    public function storePattern(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'min:1', 'max:7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration_minutes' => ['required', 'integer', 'min:15'],
            'repeat_every_weeks' => ['required', 'integer', 'min:1', 'max:12'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        $pattern = AvailabilityPattern::create($validated);

        return response()->json([
            'success' => true,
            'data' => $pattern,
            'message' => 'Modele de disponibilite crée avec succes.',
        ], 201);
    }

    public function updatePattern(Request $request, AvailabilityPattern $pattern): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'days_of_week' => ['sometimes', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'min:1', 'max:7'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'slot_duration_minutes' => ['sometimes', 'integer', 'min:15'],
            'repeat_every_weeks' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

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

    public function generateSlots(Request $request, AvailabilityPattern $pattern): JsonResponse
    {
        $validated = $request->validate([
            'until_date' => ['required', 'date', 'after:today'],
        ]);

        $untilDate = Carbon::parse($validated['until_date']);
        $slots = $pattern->generateSlots($untilDate);

        return response()->json([
            'success' => true,
            'slots_created' => count($slots),
            'message' => count($slots) . ' creneaux generes avec succes.',
        ]);
    }

    public function togglePattern(AvailabilityPattern $pattern): JsonResponse
    {
        $pattern->update([
            'is_active' => !$pattern->is_active,
        ]);

        return response()->json([
            'success' => true,
            'data' => $pattern->fresh(),
            'message' => $pattern->is_active ? 'Modele active.' : 'Modele desactive.',
        ]);
    }
}
