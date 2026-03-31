<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = EventCategory::ordered()
            ->withCount('galleries')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        // Ensure unique slug
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (EventCategory::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug.'-'.$counter++;
        }

        $category = EventCategory::create($validated);

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Catégorie créée avec succès.',
        ], 201);
    }

    public function update(Request $request, EventCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($validated['name'])) {
            $slug = Str::slug($validated['name']);
            $baseSlug = $slug;
            $counter = 1;
            while (EventCategory::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }
            $validated['slug'] = $slug;
        }

        $category->update($validated);

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
            'message' => 'Catégorie mise à jour avec succès.',
        ]);
    }

    public function destroy(EventCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catégorie supprimée avec succès.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'exists:event_categories,id'],
            'categories.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $cases = [];
        $ids = [];
        foreach ($validated['categories'] as $categoryData) {
            $cases[] = "WHEN '{$categoryData['id']}' THEN {$categoryData['sort_order']}";
            $ids[] = $categoryData['id'];
        }

        if (! empty($cases)) {
            $caseSql = implode(' ', $cases);
            EventCategory::whereIn('id', $ids)
                ->update(['sort_order' => \DB::raw("CASE id {$caseSql} END")]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordre mis à jour.',
        ]);
    }
}
