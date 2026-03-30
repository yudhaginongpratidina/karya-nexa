<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PeriodController extends Controller
{
    /**
     * GET /api/periods
     */
    public function index(): JsonResponse
    {
        try {
            $data = Period::latest()->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch periods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/periods/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $period = Period::find($id);

            if (!$period) {
                return response()->json([
                    'success' => false,
                    'message' => 'Period not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $period
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/periods
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period_name' => 'required|string|max:255|unique:periods,period_name',
                'is_finalized' => 'nullable|boolean'
            ]);

            $period = Period::create([
                'period_name' => $validated['period_name'],
                'is_finalized' => $validated['is_finalized'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Period created',
                'data' => $period
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create period',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PATCH /api/periods/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $period = Period::find($id);

            if (!$period) {
                return response()->json([
                    'success' => false,
                    'message' => 'Period not found'
                ], 404);
            }

            $validated = $request->validate([
                'period_name' => 'sometimes|required|string|max:255|unique:periods,period_name,' . $id,
                'is_finalized' => 'sometimes|boolean'
            ]);

            $period->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Period updated',
                'data' => $period
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update period',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/periods/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $period = Period::find($id);

            if (!$period) {
                return response()->json([
                    'success' => false,
                    'message' => 'Period not found'
                ], 404);
            }

            $period->delete();

            return response()->json([
                'success' => true,
                'message' => 'Period deleted'
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete period',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
