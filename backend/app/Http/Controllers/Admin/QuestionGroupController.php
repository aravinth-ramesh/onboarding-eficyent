<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuestionGroupRequest;
use App\Models\QuestionGroup;
use Illuminate\Http\JsonResponse;

class QuestionGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = QuestionGroup::withCount('questions')
            ->orderBy('order')
            ->paginate(20);

        return response()->json($groups);
    }

    public function store(QuestionGroupRequest $request): JsonResponse
    {
        $group = QuestionGroup::create($request->validated());

        return response()->json([
            'message' => 'Question group created.',
            'data' => $group,
        ], 201);
    }

    public function show(QuestionGroup $questionGroup): JsonResponse
    {
        $questionGroup->load('questions');

        return response()->json(['data' => $questionGroup]);
    }

    public function update(QuestionGroupRequest $request, QuestionGroup $questionGroup): JsonResponse
    {
        $questionGroup->update($request->validated());

        return response()->json([
            'message' => 'Question group updated.',
            'data' => $questionGroup,
        ]);
    }

    public function destroy(QuestionGroup $questionGroup): JsonResponse
    {
        $questionGroup->delete();

        return response()->json(['message' => 'Question group deleted.']);
    }
}
