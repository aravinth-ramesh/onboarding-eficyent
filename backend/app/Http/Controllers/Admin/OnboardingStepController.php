<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OnboardingStepRequest;
use App\Models\OnboardingStep;
use Illuminate\Http\JsonResponse;

class OnboardingStepController extends Controller
{
    public function index(): JsonResponse
    {
        $steps = OnboardingStep::orderBy('order')->paginate(20);

        return response()->json($steps);
    }

    public function store(OnboardingStepRequest $request): JsonResponse
    {
        $step = OnboardingStep::create($request->validated());

        return response()->json([
            'message' => 'Onboarding step created.',
            'data' => $step,
        ], 201);
    }

    public function show(OnboardingStep $onboardingStep): JsonResponse
    {
        return response()->json(['data' => $onboardingStep]);
    }

    public function update(OnboardingStepRequest $request, OnboardingStep $onboardingStep): JsonResponse
    {
        $onboardingStep->update($request->validated());

        return response()->json([
            'message' => 'Onboarding step updated.',
            'data' => $onboardingStep,
        ]);
    }

    public function destroy(OnboardingStep $onboardingStep): JsonResponse
    {
        $onboardingStep->delete();

        return response()->json(['message' => 'Onboarding step deleted.']);
    }

    public function reorder(): JsonResponse
    {
        $validated = request()->validate([
            'steps' => ['required', 'array'],
            'steps.*.id' => ['required', 'exists:onboarding_steps,id'],
            'steps.*.order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['steps'] as $stepData) {
            OnboardingStep::where('id', $stepData['id'])
                ->update(['order' => $stepData['order']]);
        }

        return response()->json(['message' => 'Steps reordered.']);
    }
}
