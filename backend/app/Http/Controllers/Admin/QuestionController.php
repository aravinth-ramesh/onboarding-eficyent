<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuestionRequest;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Question::with('group');

        if ($request->has('group_id')) {
            $query->where('question_group_id', $request->input('group_id'));
        }

        $questions = $query->orderBy('order')->paginate(20);

        return response()->json($questions);
    }

    public function store(QuestionRequest $request): JsonResponse
    {
        $question = Question::create($request->validated());

        // Create type mappings if provided
        if ($request->has('type_mappings')) {
            foreach ($request->input('type_mappings') as $mapping) {
                $question->typeMappings()->create($mapping);
            }
        }

        return response()->json([
            'message' => 'Question created.',
            'data' => $question->load('typeMappings'),
        ], 201);
    }

    public function show(Question $question): JsonResponse
    {
        $question->load(['group', 'typeMappings', 'conditionalRules']);

        return response()->json(['data' => $question]);
    }

    public function update(QuestionRequest $request, Question $question): JsonResponse
    {
        $question->update($request->validated());

        // Update type mappings if provided
        if ($request->has('type_mappings')) {
            $question->typeMappings()->delete();
            foreach ($request->input('type_mappings') as $mapping) {
                $question->typeMappings()->create($mapping);
            }
        }

        return response()->json([
            'message' => 'Question updated.',
            'data' => $question->load('typeMappings'),
        ]);
    }

    public function destroy(Question $question): JsonResponse
    {
        $question->delete();

        return response()->json(['message' => 'Question deleted.']);
    }
}
