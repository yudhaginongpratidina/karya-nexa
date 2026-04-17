<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    public function index(): JsonResponse
    {
        $periods = Period::query()
            ->withCount(['performances', 'topsisResults'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $period = Period::query()
            ->withCount(['performances', 'topsisResults'])
            ->find($id);

        if (! $period) {
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $period,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_name' => 'required|string|max:255|unique:periods,period_name',
        ]);

        $period = Period::create([
            'period_name' => $validated['period_name'],
            'is_finalized' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Periode berhasil ditambahkan.',
            'data' => $period,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $period = Period::find($id);

        if (! $period) {
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak ditemukan.',
            ], 404);
        }

        if ($period->is_finalized) {
            return response()->json([
                'success' => false,
                'message' => 'Periode yang sudah selesai tidak bisa diedit.',
            ], 422);
        }

        $validated = $request->validate([
            'period_name' => 'required|string|max:255|unique:periods,period_name,' . $id,
        ]);

        $period->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Periode berhasil diperbarui.',
            'data' => $period->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $period = Period::find($id);

        if (! $period) {
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak ditemukan.',
            ], 404);
        }

        $period->delete();

        return response()->json([
            'success' => true,
            'message' => 'Periode berhasil dihapus.',
        ]);
    }
}
