<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Criteria;
use App\Models\Performance;
use App\Models\Period;
use App\Models\TopsisResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TopsisController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:periods,id',
        ]);

        try {
            $dataset = $this->buildDataset((int) $validated['period_id']);

            return response()->json([
                'success' => true,
                'data' => $dataset,
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:periods,id',
        ]);

        DB::beginTransaction();

        try {
            $period = Period::findOrFail($validated['period_id']);

            if ($period->is_finalized) {
                throw new InvalidArgumentException('Periode ini sudah selesai dihitung.');
            }

            $calculation = $this->calculateTopsis((int) $validated['period_id']);

            TopsisResult::query()->where('period_id', $validated['period_id'])->delete();

            foreach ($calculation['results'] as $result) {
                TopsisResult::create([
                    'user_id' => $result['user_id'],
                    'period_id' => $validated['period_id'],
                    'preference_value' => $result['ci'],
                    'rank' => $result['rank'],
                ]);
            }

            $period->update([
                'is_finalized' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Perhitungan TOPSIS berhasil disimpan dan periode ditandai selesai.',
                'data' => $calculation['results'],
                'meta' => $calculation['meta'],
            ]);
        } catch (InvalidArgumentException $exception) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        $periods = Period::query()
            ->whereHas('topsisResults')
            ->withCount('topsisResults')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    public function show(int $periodId): JsonResponse
    {
        $period = Period::query()
            ->with(['topsisResults' => function ($query) {
                $query->with('user:id,name')->orderBy('rank');
            }])
            ->find($periodId);

        if (! $period || $period->topsisResults->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Hasil perhitungan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period->only(['id', 'period_name', 'is_finalized']),
                'results' => $period->topsisResults->map(function (TopsisResult $result) {
                    return [
                        'id' => $result->id,
                        'user_id' => $result->user_id,
                        'user_name' => $result->user?->name,
                        'preference_value' => (float) $result->preference_value,
                        'rank' => $result->rank,
                    ];
                })->values(),
            ],
        ]);
    }

    public function destroyByPeriod(int $periodId): JsonResponse
    {
        $period = Period::find($periodId);

        if (! $period) {
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak ditemukan.',
            ], 404);
        }

        TopsisResult::query()->where('period_id', $periodId)->delete();

        $period->update([
            'is_finalized' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hasil perhitungan berhasil dihapus dan periode dibuka kembali.',
        ]);
    }

    private function buildDataset(int $periodId): array
    {
        $criterias = Criteria::query()
            ->select(['id', 'name', 'weight', 'type'])
            ->orderBy('id')
            ->get();

        if ($criterias->isEmpty()) {
            throw new InvalidArgumentException('Kriteria belum tersedia.');
        }

        $users = User::query()
            ->select(['users.id', 'users.name'])
            ->join('performances', 'performances.user_id', '=', 'users.id')
            ->where('performances.period_id', $periodId)
            ->distinct()
            ->orderBy('users.name')
            ->get();

        if ($users->isEmpty()) {
            throw new InvalidArgumentException('Belum ada data performa pada periode tersebut.');
        }

        $decisionMatrix = $this->buildDecisionMatrix($users, $criterias, $periodId);

        return [
            'period' => Period::query()->select(['id', 'period_name', 'is_finalized'])->findOrFail($periodId),
            'criterias' => $criterias->map(function (Criteria $criteria, int $index) {
                return [
                    'id' => $criteria->id,
                    'code' => 'C' . ($index + 1),
                    'name' => $criteria->name,
                    'type' => $criteria->type,
                    'weight' => (float) $criteria->weight,
                ];
            })->values(),
            'alternatives' => $users->values()->map(function (User $user, int $index) use ($criterias, $decisionMatrix) {
                $scores = [];

                foreach ($criterias as $criteriaIndex => $criteria) {
                    $scores[] = [
                        'criteria_id' => $criteria->id,
                        'code' => 'C' . ($criteriaIndex + 1),
                        'name' => $criteria->name,
                        'score' => $decisionMatrix[$user->id][$criteria->id] ?? 0,
                    ];
                }

                return [
                    'user_id' => $user->id,
                    'alternative_code' => 'A' . ($index + 1),
                    'user_name' => $user->name,
                    'scores' => $scores,
                ];
            }),
        ];
    }

    private function calculateTopsis(int $periodId): array
    {
        $dataset = $this->buildDataset($periodId);
        $users = User::query()
            ->whereIn('id', collect($dataset['alternatives'])->pluck('user_id'))
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
        $criterias = Criteria::query()->select(['id', 'name', 'weight', 'type'])->orderBy('id')->get();

        $normalizedWeights = $this->normalizeWeights($criterias);
        $decisionMatrix = $this->buildDecisionMatrix($users, $criterias, $periodId);
        $normalizedMatrix = $this->normalizeDecisionMatrix($users, $criterias, $decisionMatrix);
        $weightedMatrix = $this->buildWeightedMatrix($users, $criterias, $normalizedMatrix, $normalizedWeights);

        [$idealPlus, $idealMinus] = $this->buildIdealSolutions($criterias, $weightedMatrix);
        $results = $this->buildPreferenceScores($users, $criterias, $weightedMatrix, $idealPlus, $idealMinus);

        usort($results, static fn (array $a, array $b): int => $b['ci'] <=> $a['ci']);

        foreach ($results as $index => &$result) {
            $result['rank'] = $index + 1;
        }
        unset($result);

        $results = $this->attachUserBreakdown($results, $users, $criterias, $decisionMatrix);

        return [
            'results' => $results,
            'meta' => [
                'period' => $dataset['period'],
                'criterias' => $dataset['criterias'],
                'normalized_weights' => $normalizedWeights,
                'ideal_plus' => $idealPlus,
                'ideal_minus' => $idealMinus,
                'decision_matrix' => $decisionMatrix,
                'normalized_matrix' => $normalizedMatrix,
                'weighted_matrix' => $weightedMatrix,
            ],
        ];
    }

    private function attachUserBreakdown(
        array $results,
        Collection $users,
        Collection $criterias,
        array $decisionMatrix
    ): array {
        $userNames = $users->pluck('name', 'id');
        $userCodes = $users->values()->mapWithKeys(function (User $user, int $index) {
            return [$user->id => 'A' . ($index + 1)];
        });

        foreach ($results as &$result) {
            $result['user_name'] = $userNames->get($result['user_id']);
            $result['alternative_code'] = $userCodes->get($result['user_id']);
            $result['criteria_values'] = $criterias->values()->map(function (Criteria $criteria, int $index) use ($result, $decisionMatrix) {
                return [
                    'criteria_id' => $criteria->id,
                    'code' => 'C' . ($index + 1),
                    'criteria_name' => $criteria->name,
                    'value' => $decisionMatrix[$result['user_id']][$criteria->id] ?? 0.0,
                ];
            })->all();
        }
        unset($result);

        return $results;
    }

    private function normalizeWeights(Collection $criterias): array
    {
        $totalWeight = (float) $criterias->sum('weight');

        if ($totalWeight <= 0) {
            throw new InvalidArgumentException('Total bobot kriteria harus lebih besar dari 0.');
        }

        $normalized = [];
        foreach ($criterias as $criteria) {
            $normalized[$criteria->id] = (float) $criteria->weight / $totalWeight;
        }

        return $normalized;
    }

    private function buildDecisionMatrix(Collection $users, Collection $criterias, int $periodId): array
    {
        $criteriaIds = $criterias->pluck('id')->all();
        $userIds = $users->pluck('id')->all();

        $performances = Performance::query()
            ->select(['user_id', 'criteria_id', 'score'])
            ->where('period_id', $periodId)
            ->whereIn('user_id', $userIds)
            ->whereIn('criteria_id', $criteriaIds)
            ->get();

        if ($performances->isEmpty()) {
            throw new InvalidArgumentException('Data performa untuk periode terpilih belum tersedia.');
        }

        $indexed = [];
        foreach ($performances as $performance) {
            $indexed[$performance->user_id][$performance->criteria_id] = (float) $performance->score;
        }

        $matrix = [];
        foreach ($users as $user) {
            foreach ($criterias as $criteria) {
                if (! isset($indexed[$user->id][$criteria->id])) {
                    throw new InvalidArgumentException(
                        'Masih ada nilai performa yang belum lengkap untuk user ' . $user->name . '.'
                    );
                }

                $matrix[$user->id][$criteria->id] = $indexed[$user->id][$criteria->id];
            }
        }

        return $matrix;
    }

    private function normalizeDecisionMatrix(Collection $users, Collection $criterias, array $matrix): array
    {
        $divider = [];

        foreach ($criterias as $criteria) {
            $sumSquares = 0.0;
            foreach ($users as $user) {
                $value = $matrix[$user->id][$criteria->id];
                $sumSquares += $value ** 2;
            }

            $divider[$criteria->id] = sqrt($sumSquares);
        }

        $normalized = [];
        foreach ($users as $user) {
            foreach ($criterias as $criteria) {
                $denominator = $divider[$criteria->id];
                $value = $matrix[$user->id][$criteria->id];
                $normalized[$user->id][$criteria->id] = $denominator > 0 ? $value / $denominator : 0.0;
            }
        }

        return $normalized;
    }

    private function buildWeightedMatrix(
        Collection $users,
        Collection $criterias,
        array $normalizedMatrix,
        array $normalizedWeights
    ): array {
        $weighted = [];

        foreach ($users as $user) {
            foreach ($criterias as $criteria) {
                $weighted[$user->id][$criteria->id] =
                    $normalizedMatrix[$user->id][$criteria->id] * $normalizedWeights[$criteria->id];
            }
        }

        return $weighted;
    }

    private function buildIdealSolutions(Collection $criterias, array $weightedMatrix): array
    {
        $idealPlus = [];
        $idealMinus = [];

        foreach ($criterias as $criteria) {
            $values = array_column($weightedMatrix, $criteria->id);
            $isCost = strtolower((string) $criteria->type) === 'cost';

            $idealPlus[$criteria->id] = $isCost ? min($values) : max($values);
            $idealMinus[$criteria->id] = $isCost ? max($values) : min($values);
        }

        return [$idealPlus, $idealMinus];
    }

    private function buildPreferenceScores(
        Collection $users,
        Collection $criterias,
        array $weightedMatrix,
        array $idealPlus,
        array $idealMinus
    ): array {
        $results = [];

        foreach ($users as $user) {
            $dPlus = 0.0;
            $dMinus = 0.0;

            foreach ($criterias as $criteria) {
                $score = $weightedMatrix[$user->id][$criteria->id];
                $dPlus += ($score - $idealPlus[$criteria->id]) ** 2;
                $dMinus += ($score - $idealMinus[$criteria->id]) ** 2;
            }

            $dPlus = sqrt($dPlus);
            $dMinus = sqrt($dMinus);
            $denominator = $dPlus + $dMinus;

            $results[] = [
                'user_id' => $user->id,
                'd_plus' => $dPlus,
                'd_minus' => $dMinus,
                'ci' => $denominator > 0 ? $dMinus / $denominator : 0.0,
            ];
        }

        return $results;
    }
}
