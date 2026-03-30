<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Criteria;
use App\Models\TopsisResult;
use Illuminate\Support\Facades\DB;

class TopsisController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id'
        ]);

        DB::beginTransaction();

        try {
            $users = User::with('performances')->get();
            $criterias = Criteria::all();

            if ($users->isEmpty() || $criterias->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak lengkap'
                ], 400);
            }

            // STEP 1: NORMALISASI BOBOT
            $totalWeight = $criterias->sum('weight');

            $criterias = $criterias->map(function ($c) use ($totalWeight) {
                $c->normalized_weight = $c->weight / $totalWeight;
                return $c;
            });

            // STEP 2: MATRIX R
            $matrix = [];

            foreach ($users as $user) {
                foreach ($criterias as $c) {
                    $value = optional(
                        $user->performances->firstWhere('criteria_id', $c->id)
                    )->value ?? 0;

                    $matrix[$user->id][$c->id] = $value;
                }
            }

            // STEP 3: NORMALISASI R
            $divider = [];

            foreach ($criterias as $c) {
                $sum = 0;
                foreach ($users as $user) {
                    $sum += pow($matrix[$user->id][$c->id], 2);
                }
                $divider[$c->id] = sqrt($sum);
            }

            $R = [];

            foreach ($users as $user) {
                foreach ($criterias as $c) {
                    $R[$user->id][$c->id] =
                        $divider[$c->id] == 0
                        ? 0
                        : $matrix[$user->id][$c->id] / $divider[$c->id];
                }
            }

            // STEP 4: MATRIX V
            $V = [];

            foreach ($users as $user) {
                foreach ($criterias as $c) {
                    $V[$user->id][$c->id] =
                        $R[$user->id][$c->id] * $c->normalized_weight;
                }
            }

            // STEP 5: IDEAL + & -
            $idealPlus = [];
            $idealMinus = [];

            foreach ($criterias as $c) {
                $values = array_column($V, $c->id);

                if ($c->type == 'benefit') {
                    $idealPlus[$c->id] = max($values);
                    $idealMinus[$c->id] = min($values);
                } else {
                    $idealPlus[$c->id] = min($values);
                    $idealMinus[$c->id] = max($values);
                }
            }

            // STEP 6: JARAK
            $results = [];

            foreach ($users as $user) {
                $dPlus = 0;
                $dMinus = 0;

                foreach ($criterias as $c) {
                    $dPlus += pow($V[$user->id][$c->id] - $idealPlus[$c->id], 2);
                    $dMinus += pow($V[$user->id][$c->id] - $idealMinus[$c->id], 2);
                }

                $dPlus = sqrt($dPlus);
                $dMinus = sqrt($dMinus);

                $ci = $dMinus / ($dPlus + $dMinus);

                $results[] = [
                    'user_id' => $user->id,
                    'ci' => $ci
                ];
            }

            // STEP 7: RANKING
            usort($results, fn($a, $b) => $b['ci'] <=> $a['ci']);

            foreach ($results as $index => $r) {
                TopsisResult::updateOrCreate(
                    [
                        'user_id' => $r['user_id'],
                        'period_id' => $request->period_id
                    ],
                    [
                        'preference_value' => $r['ci'],
                        'rank' => $index + 1
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // GET ALL
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => TopsisResult::with(['user', 'period'])->get()
        ]);
    }

    // DETAIL
    public function show($id)
    {
        $data = TopsisResult::with(['user', 'period'])->find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // DELETE
    public function destroy($id)
    {
        $data = TopsisResult::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Not found'
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }
}