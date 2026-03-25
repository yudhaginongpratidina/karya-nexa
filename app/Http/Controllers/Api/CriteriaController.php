<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Criteria;

class CriteriaController extends Controller
{
    public function index()
    {
        $data = Criteria::with('category')->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'List of criterias',
            'data' => $data
        ], 200);
    }

    public function show($id)
    {
        $criteria = Criteria::with('category')->find($id);

        if (!$criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Criteria not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $criteria
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0|max:1',
            'type' => 'required|in:benefit,cost'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $criteria = Criteria::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Criteria created',
            'data' => $criteria
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $criteria = Criteria::find($id);

        if (!$criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Criteria not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'weight' => 'sometimes|numeric|min:0|max:1',
            'type' => 'sometimes|in:benefit,cost'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $criteria->update($validator->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Criteria updated',
                'data' => $criteria
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $criteria = Criteria::find($id);

        if (!$criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Criteria not found'
            ], 404);
        }

        $criteria->delete();

        return response()->json([
            'success' => true,
            'message' => 'Criteria deleted'
        ], 200);
    }
}
