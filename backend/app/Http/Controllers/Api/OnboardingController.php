<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveAnswersRequest;
use App\Http\Requests\Api\SetUserTypeRequest;
use App\Models\Question;
use App\Models\QuestionTypeMapping;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use App\Services\AnswerService;
use App\Services\ConditionalRuleEngine;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(
        private OnboardingService $onboardingService,
        private AnswerService $answerService,
        private ConditionalRuleEngine $ruleEngine,
    ) {}

    /**
     * Format onboarding data consistently for all endpoints.
     */
    private function formatOnboardingResponse(UserOnboarding $onboarding): array
    {
        $onboarding->load(['steps', 'userType', 'subcategory']);

        $steps = $onboarding->steps->map(fn (UserOnboardingStep $step) => [
            'id' => $step->id,
            'name' => $step->name,
            'component_key' => $step->component_key,
            'order' => $step->order,
            'status' => $step->status,
            'config' => $step->config,
            'started_at' => $step->started_at,
            'completed_at' => $step->completed_at,
        ]);

        return [
            'id' => $onboarding->id,
            'status' => $onboarding->status,
            'user_type' => $onboarding->userType,
            'subcategory' => $onboarding->subcategory,
            'template_version' => $onboarding->template_version,
            'current_step' => $steps->firstWhere('id', $onboarding->current_step_id),
            'steps' => $steps,
            'started_at' => $onboarding->started_at,
            'completed_at' => $onboarding->completed_at,
        ];
    }

    /**
     * Get or initialize the user's onboarding state.
     */
    public function status(): JsonResponse
    {
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding) {
            $onboarding = $this->onboardingService->initializeForUser($user);
        }

        return response()->json([
            'data' => $this->formatOnboardingResponse($onboarding),
        ]);
    }

    /**
     * Set user type (and optionally subcategory) for onboarding.
     */
    public function setUserType(SetUserTypeRequest $request): JsonResponse
    {
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding) {
            return response()->json(['message' => 'Onboarding not initialized.'], 404);
        }

        $onboarding = $this->onboardingService->setUserType(
            $onboarding,
            $request->validated('user_type_id'),
            $request->validated('subcategory_id'),
        );

        return response()->json([
            'message' => 'User type set successfully.',
            'data' => $this->formatOnboardingResponse($onboarding),
        ]);
    }

    /**
     * Get questions for the current onboarding step.
     */
    public function questions(): JsonResponse
    {
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding || !$onboarding->user_type_id) {
            return response()->json(['message' => 'Please select a user type first.'], 422);
        }

        // Get questions mapped to the user's type/subcategory, grouped by question group
        $mappings = QuestionTypeMapping::where('user_type_id', $onboarding->user_type_id)
            ->where(function ($query) use ($onboarding) {
                $query->whereNull('user_type_subcategory_id');
                if ($onboarding->user_type_subcategory_id) {
                    $query->orWhere('user_type_subcategory_id', $onboarding->user_type_subcategory_id);
                }
            })
            ->where('is_active', true)
            ->with(['question.group', 'question.conditionalRules' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('order')
            ->get();

        // Deduplicate mappings — keep one per question (prefer subcategory-specific over generic)
        $mappings = $mappings->unique(fn ($m) => $m->question_id)->values();

        // Get existing answers
        $answers = $user->answers()
            ->where('user_onboarding_id', $onboarding->id)
            ->pluck('value', 'question_id')
            ->toArray();

        // Group questions by their question group
        $grouped = $mappings->groupBy(fn ($m) => $m->question->group->id);

        $groups = $grouped->map(function ($mappingsInGroup) use ($answers) {
            $group = $mappingsInGroup->first()->question->group;

            $questions = $mappingsInGroup->map(function ($mapping) use ($answers) {
                $question = $mapping->question;
                $rules = $question->conditionalRules;

                return [
                    'id' => $question->id,
                    'label' => $question->label,
                    'description' => $question->description,
                    'type' => $question->type,
                    'options' => $question->options,
                    'is_required' => $mapping->is_required ?? $question->is_required,
                    'order' => $mapping->order ?? $question->order,
                    'placeholder' => $question->placeholder,
                    'help_text' => $question->help_text,
                    'validation_rules' => $question->validation_rules,
                    'answer' => $answers[$question->id] ?? null,
                    'conditional_rules' => $rules->map(fn ($rule) => [
                        'parent_question_id' => $rule->parent_question_id,
                        'comparison_type' => $rule->comparison_type,
                        'trigger_value' => $rule->trigger_value,
                        'action' => $rule->action,
                        'logical_operator' => $rule->logical_operator ?? 'and',
                    ])->values()->toArray(),
                ];
            })->sortBy('order')->values();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'order' => $group->order,
                'questions' => $questions,
            ];
        })->sortBy('order')->values();

        return response()->json(['data' => $groups]);
    }

    /**
     * Save answers for questions.
     */
    public function saveAnswers(SaveAnswersRequest $request): JsonResponse
    {
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding) {
            return response()->json(['message' => 'Onboarding not initialized.'], 404);
        }

        $this->answerService->saveBulkAnswers(
            $user,
            $onboarding,
            $request->validated('answers'),
        );

        return response()->json(['message' => 'Answers saved successfully.']);
    }

    /**
     * Complete the current step and advance to the next.
     */
    public function completeStep(UserOnboardingStep $step): JsonResponse
    {
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding || $step->user_onboarding_id !== $onboarding->id) {
            return response()->json(['message' => 'Step not found.'], 404);
        }

        $onboarding = $this->onboardingService->completeStep($onboarding, $step);

        return response()->json([
            'message' => 'Step completed.',
            'data' => $this->formatOnboardingResponse($onboarding),
        ]);
    }

    /**
     * Go back to the previous step.
     */
    public function previousStep(UserOnboardingStep $step): JsonResponse
    {
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding || $step->user_onboarding_id !== $onboarding->id) {
            return response()->json(['message' => 'Step not found.'], 404);
        }

        $onboarding = $this->onboardingService->goToPreviousStep($onboarding, $step);

        return response()->json([
            'message' => 'Returned to previous step.',
            'data' => $this->formatOnboardingResponse($onboarding),
        ]);
    }
}
