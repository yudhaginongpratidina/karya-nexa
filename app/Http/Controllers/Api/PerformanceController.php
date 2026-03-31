<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Criteria;
use App\Models\Performance;
use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerformanceController extends Controller
{
    /**
     * GET /api/performances
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if ($request->boolean('summary')) {
                $data = Performance::query()
                    ->select('user_id', 'period_id', DB::raw('COUNT(*) as criteria_count'), DB::raw('AVG(score) as average_score'))
                    ->with([
                        'user:id,name',
                        'period:id,period_name,is_finalized',
                    ])
                    ->groupBy('user_id', 'period_id')
                    ->orderByDesc('period_id')
                    ->orderBy('user_id')
                    ->get();

                return response()->json([
                    'success' => true,
                    'data' => $data,
                ], 200);
            }

            $data = Performance::with(['user', 'criteria', 'period'])
                ->latest()
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch performances',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/performances/matrix
     */
    public function matrix(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_id' => 'required|exists:periods,id',
        ]);

        $period = Period::findOrFail($validated['period_id']);

        $criteria = Criteria::query()
            ->select(['id', 'name', 'type'])
            ->orderBy('id')
            ->get();

        $performanceMap = Performance::query()
            ->where('user_id', $validated['user_id'])
            ->where('period_id', $validated['period_id'])
            ->get()
            ->keyBy('criteria_id');

        $rows = $criteria->map(function (Criteria $item) use ($performanceMap) {
            $performance = $performanceMap->get($item->id);

            return [
                'criteria_id' => $item->id,
                'criteria_name' => $item->name,
                'criteria_type' => $item->type,
                'performance_id' => $performance?->id,
                'score' => $performance?->score,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * PATCH /api/performances/matrix
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_id' => 'required|exists:periods,id',
            'scores' => 'required|array|min:1',
            'scores.*.criteria_id' => 'required|exists:criterias,id',
            'scores.*.score' => 'required|numeric|min:0|max:100',
        ]);

        $period = Period::findOrFail($validated['period_id']);

        if ($period->is_finalized) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update finalized period data',
            ], 403);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['scores'] as $row) {
                Performance::updateOrCreate(
                    [
                        'user_id' => $validated['user_id'],
                        'period_id' => $validated['period_id'],
                        'criteria_id' => $row['criteria_id'],
                    ],
                    [
                        'score' => $row['score'],
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Performance matrix updated',
        ]);
    }

    /**
     * GET /api/performances/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $performance = Performance::with(['user', 'criteria', 'period'])
                ->find($id);

            if (!$performance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Performance not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $performance,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/performances
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'criteria_id' => 'required|exists:criterias,id',
                'period_id' => 'required|exists:periods,id',
                'score' => 'required|numeric|min:0|max:100',
            ]);

            $period = Period::find($validated['period_id']);
            if ($period->is_finalized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot add performance to finalized period',
                ], 403);
            }

            $exists = Performance::where([
                'user_id' => $validated['user_id'],
                'criteria_id' => $validated['criteria_id'],
                'period_id' => $validated['period_id'],
            ])->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Performance already exists for this combination',
                ], 409);
            }

            $performance = Performance::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Performance created',
                'data' => $performance,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create performance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/performances/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $performance = Performance::find($id);

            if (!$performance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Performance not found',
                ], 404);
            }

            if ($performance->period->is_finalized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update finalized period data',
                ], 403);
            }

            $validated = $request->validate([
                'score' => 'sometimes|required|numeric|min:0|max:100',
            ]);

            $performance->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Performance updated',
                'data' => $performance,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update performance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/performances/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $performance = Performance::find($id);

            if (!$performance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Performance not found',
                ], 404);
            }

            if ($performance->period->is_finalized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete finalized data',
                ], 403);
            }

            $performance->delete();

            return response()->json([
                'success' => true,
                'message' => 'Performance deleted',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete performance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
