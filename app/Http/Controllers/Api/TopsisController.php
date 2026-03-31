<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Criteria;
use App\Models\Performance;
use App\Models\TopsisResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TopsisController extends Controller
{
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:periods,id',
        ]);

        DB::beginTransaction();

        try {
            $calculation = $this->calculateTopsis((int) $validated['period_id']);
            $results = $calculation['results'];

            foreach ($results as $result) {
                TopsisResult::updateOrCreate(
                    [
                        'user_id' => $result['user_id'],
                        'period_id' => $validated['period_id'],
                    ],
                    [
                        'preference_value' => $result['ci'],
                        'rank' => $result['rank'],
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $results,
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
        return response()->json([
            'success' => true,
            'data' => TopsisResult::with(['user', 'period'])->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $data = TopsisResult::with(['user', 'period'])->find($id);

        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => 'Not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $data = TopsisResult::find($id);

        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => 'Not found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateTopsis(int $periodId): array
    {
        $users = User::query()->select(['id', 'name'])->orderBy('id')->get();
        $criterias = Criteria::query()->select(['id', 'name', 'weight', 'type'])->orderBy('id')->get();

        if ($users->isEmpty() || $criterias->isEmpty()) {
            throw new InvalidArgumentException('Data user atau kriteria belum tersedia.');
        }

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

        $results = $this->attachUserAndCriteriaBreakdown($results, $users, $criterias, $decisionMatrix);

        return [
            'results' => $results,
            'meta' => [
                'normalized_weights' => $normalizedWeights,
                'ideal_plus' => $idealPlus,
                'ideal_minus' => $idealMinus,
                'stability_analysis' => $this->analyzeWeightSensitivity(
                    $users,
                    $criterias,
                    $normalizedMatrix,
                    $normalizedWeights,
                    $results
                ),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @param array<int, array<int, float>> $decisionMatrix
     * @return array<int, array<string, mixed>>
     */
    private function attachUserAndCriteriaBreakdown(
        array $results,
        Collection $users,
        Collection $criterias,
        array $decisionMatrix
    ): array {
        $userNames = $users->pluck('name', 'id');

        foreach ($results as &$result) {
            $userId = (int) $result['user_id'];
            $criteriaValues = [];

            foreach ($criterias as $criteria) {
                $criteriaValues[] = [
                    'criteria_id' => $criteria->id,
                    'criteria_name' => $criteria->name,
                    'value' => $decisionMatrix[$userId][$criteria->id] ?? 0.0,
                ];
            }

            $result['user_name'] = $userNames->get($userId);
            $result['criteria_values'] = $criteriaValues;
        }
        unset($result);

        return $results;
    }

    /**
     * @return array<int, float>
     */
    private function normalizeWeights(Collection $criterias): array
    {
        $totalWeight = (float) $criterias->sum('weight');

        if ($totalWeight <= 0) {
            throw new InvalidArgumentException('Total bobot kriteria harus lebih besar dari 0.');
        }

        $normalized = [];
        foreach ($criterias as $criteria) {
            $weight = (float) $criteria->weight;

            if ($weight < 0) {
                throw new InvalidArgumentException(sprintf(
                    'Bobot kriteria %s tidak boleh negatif.',
                    $criteria->name
                ));
            }

            $normalized[$criteria->id] = $weight / $totalWeight;
        }

        return $normalized;
    }

    /**
     * @return array<int, array<int, float>>
     */
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
                $matrix[$user->id][$criteria->id] = $indexed[$user->id][$criteria->id] ?? 0.0;
            }
        }

        return $matrix;
    }

    /**
     * @param array<int, array<int, float>> $matrix
     * @return array<int, array<int, float>>
     */
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

    /**
     * @param array<int, array<int, float>> $normalizedMatrix
     * @param array<int, float> $normalizedWeights
     * @return array<int, array<int, float>>
     */
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

    /**
     * @param array<int, array<int, float>> $weightedMatrix
     * @return array{array<int, float>, array<int, float>}
     */
    private function buildIdealSolutions(Collection $criterias, array $weightedMatrix): array
    {
        $idealPlus = [];
        $idealMinus = [];

        foreach ($criterias as $criteria) {
            $values = array_column($weightedMatrix, $criteria->id);

            if ($values === []) {
                $idealPlus[$criteria->id] = 0.0;
                $idealMinus[$criteria->id] = 0.0;
                continue;
            }

            $type = strtolower((string) $criteria->type);
            $isCost = $type === 'cost';

            $idealPlus[$criteria->id] = $isCost ? min($values) : max($values);
            $idealMinus[$criteria->id] = $isCost ? max($values) : min($values);
        }

        return [$idealPlus, $idealMinus];
    }

    /**
     * @param array<int, array<int, float>> $weightedMatrix
     * @param array<int, float> $idealPlus
     * @param array<int, float> $idealMinus
     * @return array<int, array{user_id: int, ci: float}>
     */
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
                'ci' => $denominator > 0 ? $dMinus / $denominator : 0.0,
            ];
        }

        return $results;
    }

    /**
     * @param array<int, array<int, float>> $normalizedMatrix
     * @param array<int, float> $normalizedWeights
     * @param array<int, array{user_id: int, ci: float, rank?: int}> $baseResults
     * @return array<int, array<string, mixed>>
     */
    private function analyzeWeightSensitivity(
        Collection $users,
        Collection $criterias,
        array $normalizedMatrix,
        array $normalizedWeights,
        array $baseResults
    ): array {
        $baseByUser = [];
        foreach ($baseResults as $item) {
            $baseByUser[$item['user_id']] = [
                'ci' => $item['ci'],
                'rank' => $item['rank'] ?? null,
            ];
        }

        $analysis = [];
        foreach ($criterias as $criteria) {
            $adjustedWeights = $normalizedWeights;
            $adjustedWeights[$criteria->id] = $adjustedWeights[$criteria->id] * 1.1;

            $sumAdjusted = array_sum($adjustedWeights);
            if ($sumAdjusted <= 0) {
                continue;
            }

            foreach ($adjustedWeights as $id => $weight) {
                $adjustedWeights[$id] = $weight / $sumAdjusted;
            }

            $weightedMatrix = $this->buildWeightedMatrix($users, $criterias, $normalizedMatrix, $adjustedWeights);
            [$idealPlus, $idealMinus] = $this->buildIdealSolutions($criterias, $weightedMatrix);
            $scenarioResults = $this->buildPreferenceScores($users, $criterias, $weightedMatrix, $idealPlus, $idealMinus);
            usort($scenarioResults, static fn (array $a, array $b): int => $b['ci'] <=> $a['ci']);

            foreach ($scenarioResults as $index => &$scenarioResult) {
                $scenarioResult['rank'] = $index + 1;
            }
            unset($scenarioResult);

            $impacts = [];
            foreach ($scenarioResults as $scenarioResult) {
                $base = $baseByUser[$scenarioResult['user_id']] ?? ['ci' => 0.0, 'rank' => null];
                $impacts[] = [
                    'user_id' => $scenarioResult['user_id'],
                    'delta_ci' => $scenarioResult['ci'] - $base['ci'],
                    'old_rank' => $base['rank'],
                    'new_rank' => $scenarioResult['rank'],
                    'rank_changed' => $base['rank'] !== null && $base['rank'] !== $scenarioResult['rank'],
                ];
            }

            $analysis[] = [
                'criteria_id' => $criteria->id,
                'criteria_name' => $criteria->name,
                'scenario' => 'weight_plus_10_percent',
                'adjusted_weights' => $adjustedWeights,
                'impacts' => $impacts,
            ];
        }

        return $analysis;
    }
}
