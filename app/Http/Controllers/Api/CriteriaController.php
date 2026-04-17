<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Criteria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CriteriaController extends Controller
{
    public function index(): JsonResponse
    {
        $categoryId = request()->integer('category_id');

        $criterias = Criteria::query()
            ->with('category:id,name')
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $criterias,
            'meta' => [
                'selected_category_id' => $categoryId ?: null,
                'categories' => Category::query()->select(['id', 'name'])->orderBy('name')->get(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $criteria = Criteria::query()
            ->with('category:id,name')
            ->find($id);

        if (! $criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Kriteria tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $criteria,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:benefit,cost',
            'weight' => 'nullable|numeric|min:0.0001|max:999999.9999',
        ]);

        $criteria = Criteria::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'weight' => $validated['weight'] ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kriteria berhasil ditambahkan.',
            'data' => $criteria->load('category:id,name'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $criteria = Criteria::find($id);

        if (! $criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Kriteria tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:benefit,cost',
            'weight' => 'nullable|numeric|min:0.0001|max:999999.9999',
        ]);

        $criteria->update([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'weight' => $validated['weight'] ?? $criteria->weight,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kriteria berhasil diperbarui.',
            'data' => $criteria->fresh()->load('category:id,name'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $criteria = Criteria::find($id);

        if (! $criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Kriteria tidak ditemukan.',
            ], 404);
        }

        $criteria->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kriteria berhasil dihapus.',
        ]);
    }
}
