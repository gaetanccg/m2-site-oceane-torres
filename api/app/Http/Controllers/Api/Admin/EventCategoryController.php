<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderEventCategoriesRequest;
use App\Http\Requests\Admin\StoreEventCategoryRequest;
use App\Http\Requests\Admin\UpdateEventCategoryRequest;
use App\Models\EventCategory;
use Illuminate\Http\JsonResponse;
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

    public function store(StoreEventCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

    public function update(UpdateEventCategoryRequest $request, EventCategory $category): JsonResponse
    {
        $validated = $request->validated();

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

    public function reorder(ReorderEventCategoriesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $cases = [];
        $bindings = [];
        $ids = [];
        foreach ($validated['categories'] as $categoryData) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $categoryData['id'];
            $bindings[] = (int) $categoryData['sort_order'];
            $ids[] = $categoryData['id'];
        }

        if (! empty($cases)) {
            $caseSql = implode(' ', $cases);
            EventCategory::whereIn('id', $ids)
                ->update(['sort_order' => \DB::raw("CASE id {$caseSql} END", $bindings)]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordre mis à jour.',
        ]);
    }
}
