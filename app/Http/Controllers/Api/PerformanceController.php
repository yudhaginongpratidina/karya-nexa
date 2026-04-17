<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Performance;
use App\Models\Period;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    public function index(): JsonResponse
    {
        $periodId = request()->integer('period_id');

        $groups = Performance::query()
            ->select('user_id', 'period_id', DB::raw('COUNT(*) as criteria_count'), DB::raw('AVG(score) as average_score'))
            ->with([
                'user:id,name',
                'period:id,period_name,is_finalized',
            ])
            ->when($periodId, fn ($query) => $query->where('period_id', $periodId))
            ->groupBy('user_id', 'period_id')
            ->orderByDesc('period_id')
            ->orderBy('user_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $groups,
            'meta' => [
                'selected_period_id' => $periodId ?: null,
                'periods' => Period::query()
                    ->select(['id', 'period_name', 'is_finalized'])
                    ->orderByDesc('created_at')
                    ->get(),
            ],
        ]);
    }

    public function formOptions(): JsonResponse
    {
        $categories = Category::query()
            ->with(['criterias' => function ($query) {
                $query->select(['id', 'category_id', 'name', 'type', 'weight'])
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => User::query()->select(['id', 'name'])->where('role', 'user')->orderBy('name')->get(),
                'periods' => Period::query()->select(['id', 'period_name', 'is_finalized'])->orderByDesc('created_at')->get(),
                'categories' => $categories,
            ],
        ]);
    }

    public function group(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_id' => 'required|exists:periods,id',
        ]);

        $period = Period::findOrFail($validated['period_id']);

        $categories = Category::query()
            ->with(['criterias' => function ($query) use ($validated) {
                $query->select(['id', 'category_id', 'name', 'type', 'weight'])
                    ->with(['performances' => function ($performanceQuery) use ($validated) {
                        $performanceQuery
                            ->select(['id', 'criteria_id', 'user_id', 'period_id', 'score'])
                            ->where('user_id', $validated['user_id'])
                            ->where('period_id', $validated['period_id']);
                    }])
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = $categories->map(function (Category $category) {
            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'criterias' => $category->criterias->map(function ($criteria) {
                    $performance = $criteria->performances->first();

                    return [
                        'criteria_id' => $criteria->id,
                        'criteria_name' => $criteria->name,
                        'type' => $criteria->type,
                        'weight' => (float) $criteria->weight,
                        'score' => $performance?->score,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => User::query()->select(['id', 'name'])->find($validated['user_id']),
                'period' => $period->only(['id', 'period_name', 'is_finalized']),
                'rows' => $rows,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->saveGroup($request, false);
    }

    public function update(Request $request): JsonResponse
    {
        return $this->saveGroup($request, true);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_id' => 'required|exists:periods,id',
        ]);

        $period = Period::findOrFail($validated['period_id']);
        if ($period->is_finalized) {
            return response()->json([
                'success' => false,
                'message' => 'Periode yang sudah selesai tidak bisa dihapus performanya.',
            ], 422);
        }

        Performance::query()
            ->where('user_id', $validated['user_id'])
            ->where('period_id', $validated['period_id'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data performa berhasil dihapus.',
        ]);
    }

    private function saveGroup(Request $request, bool $isUpdate): JsonResponse
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
                'message' => 'Periode yang sudah selesai tidak bisa diubah.',
            ], 422);
        }

        $existingCount = Performance::query()
            ->where('user_id', $validated['user_id'])
            ->where('period_id', $validated['period_id'])
            ->count();

        if (! $isUpdate && $existingCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Data performa user pada periode tersebut sudah ada. Gunakan edit.',
            ], 409);
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
            'message' => $isUpdate
                ? 'Data performa berhasil diperbarui.'
                : 'Data performa berhasil ditambahkan.',
        ]);
    }
}
