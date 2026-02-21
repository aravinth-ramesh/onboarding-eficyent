<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConditionalRuleRequest;
use App\Models\ConditionalRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConditionalRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ConditionalRule::with(['question', 'parentQuestion']);

        if ($request->has('question_id')) {
            $query->where('question_id', $request->input('question_id'));
        }

        $rules = $query->paginate(20);

        return response()->json($rules);
    }

    public function store(ConditionalRuleRequest $request): JsonResponse
    {
        $rule = ConditionalRule::create($request->validated());

        return response()->json([
            'message' => 'Conditional rule created.',
            'data' => $rule->load(['question', 'parentQuestion']),
        ], 201);
    }

    public function show(ConditionalRule $conditionalRule): JsonResponse
    {
        $conditionalRule->load(['question', 'parentQuestion']);

        return response()->json(['data' => $conditionalRule]);
    }

    public function update(ConditionalRuleRequest $request, ConditionalRule $conditionalRule): JsonResponse
    {
        $conditionalRule->update($request->validated());

        return response()->json([
            'message' => 'Conditional rule updated.',
            'data' => $conditionalRule->load(['question', 'parentQuestion']),
        ]);
    }

    public function destroy(ConditionalRule $conditionalRule): JsonResponse
    {
        $conditionalRule->delete();

        return response()->json(['message' => 'Conditional rule deleted.']);
    }
}
