<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveAnswersRequest;
use App\Http\Requests\Api\SetUserTypeRequest;
use App\Http\Requests\Api\UploadFileAnswerRequest;
use App\Models\Question;
use App\Models\QuestionTypeMapping;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use App\Services\AnswerService;
use App\Services\ChecksumValidator;
use App\Services\ConditionalRuleEngine;
use App\Services\CountryRegistrationService;
use App\Services\DocumentValidationService;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OnboardingController extends Controller
{
    public function __construct(
        private OnboardingService $onboardingService,
        private AnswerService $answerService,
        private ConditionalRuleEngine $ruleEngine,
        private CountryRegistrationService $registrationService,
        private ChecksumValidator $checksumValidator,
        private DocumentValidationService $documentValidator,
    ) {}

    /**
     * Format onboarding data consistently for all endpoints.
     */
    private function formatOnboardingResponse(UserOnboarding $onboarding): array
    {
        $onboarding->load(['steps', 'userType', 'subcategory']);

        $steps = $onboarding->steps
            ->where('status', '!=', 'skipped')
            ->values()
            ->map(fn (UserOnboardingStep $step) => [
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
            'country_code' => $onboarding->country_code,
            'registration_details' => $onboarding->registration_details,
            'template_version' => $onboarding->template_version,
            'current_step' => $steps->firstWhere('id', $onboarding->current_step_id),
            'steps' => $steps,
            'started_at' => $onboarding->started_at,
            'completed_at' => $onboarding->completed_at,
        ];
    }

    /**
     * Country registration catalog for the user's organization category,
     * plus any previously saved selection.
     */
    public function registrationCatalog(): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding) {
            return response()->json(['message' => 'Onboarding not initialized.'], 404);
        }

        $category = $this->registrationService->categoryForType($onboarding->userType);
        $catalog = $this->registrationService->catalogForCategory($category);

        return response()->json(['data' => [
            'countries' => $this->registrationService->countries(),
            'category' => $category,
            'default_fields' => $catalog['default_fields'],
            'overrides' => $catalog['overrides'],
            'selected' => [
                'country_code' => $onboarding->country_code,
                'values' => $this->extractRegistrationValues($onboarding->registration_details),
            ],
        ]]);
    }

    /**
     * Save the country of incorporation and its registration identifiers.
     * Enforces required fields and per-field format patterns server-side.
     */
    public function saveRegistration(Request $request): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding) {
            return response()->json(['message' => 'Onboarding not initialized.'], 404);
        }

        if ($this->isSubmitted($onboarding)) {
            return response()->json(['message' => 'Your application has already been submitted and can no longer be edited.'], 403);
        }

        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'values' => ['array'],
        ]);

        $countryCode = strtoupper($validated['country_code']);
        if (!array_key_exists($countryCode, config('country_registrations.countries', []))) {
            return response()->json(['message' => 'Unknown country.'], 422);
        }

        $category = $this->registrationService->categoryForType($onboarding->userType);
        $fields = $this->registrationService->fieldsFor($countryCode, $category);
        $values = $validated['values'] ?? [];

        $errors = [];
        $stored = [];
        foreach ($fields as $field) {
            $value = trim((string) ($values[$field['key']] ?? ''));

            if (($field['required'] ?? false) && $value === '') {
                $errors["values.{$field['key']}"] = ["{$field['label']} is required."];
                continue;
            }

            if ($value !== '' && !empty($field['pattern'])) {
                if (preg_match('#' . $field['pattern'] . '#u', $value) !== 1) {
                    $errors["values.{$field['key']}"] = [$field['pattern_message'] ?? "{$field['label']} format is invalid."];
                    continue;
                }
            }

            if ($value !== '' && !empty($field['checksum'])) {
                if (!$this->checksumValidator->isValid($field['checksum'], $value)) {
                    $errors["values.{$field['key']}"] = ["{$field['label']} failed the check-digit validation. Please re-check the number."];
                    continue;
                }
            }

            if ($value !== '') {
                $stored[$field['key']] = ['label' => $field['label'], 'value' => $value];
            }
        }

        if (!empty($errors)) {
            return response()->json(['message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
        }

        $onboarding->update([
            'country_code' => $countryCode,
            'registration_details' => $stored,
        ]);

        return response()->json([
            'message' => 'Registration details saved.',
            'data' => $this->formatOnboardingResponse($onboarding->fresh()),
        ]);
    }

    /**
     * Whether the application is locked from further edits. A submitted
     * (completed) application is read-only; admin-requested changes are
     * handled through the separate notification-resolve endpoints.
     */
    private function isSubmitted(UserOnboarding $onboarding): bool
    {
        return $onboarding->status === 'completed';
    }

    /**
     * Flatten stored registration details ({key:{label,value}}) to {key:value}.
     */
    private function extractRegistrationValues(?array $details): array
    {
        $out = [];
        foreach ($details ?? [] as $key => $entry) {
            $out[$key] = is_array($entry) ? ($entry['value'] ?? '') : $entry;
        }

        return $out;
    }

    /**
     * Get or initialize the user's onboarding state.
     */
    public function status(): JsonResponse
    {
        /**@disregard */
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
        /**@disregard */
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
        /**@disregard */
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

        // Get existing answers (with files for file-type questions)
        $answerModels = $user->answers()
            ->where('user_onboarding_id', $onboarding->id)
            ->with('files')
            ->get()
            ->keyBy('question_id');

        $answers = $answerModels->mapWithKeys(fn (UserAnswer $a) => [$a->question_id => $a->value])->toArray();

        // Group questions by their question group
        $grouped = $mappings->groupBy(fn ($m) => $m->question->group->id);

        $groups = $grouped->map(function ($mappingsInGroup) use ($answers, $answerModels) {
            $group = $mappingsInGroup->first()->question->group;

            $questions = $mappingsInGroup->map(function ($mapping) use ($answers, $answerModels) {
                $question = $mapping->question;
                $rules = $question->conditionalRules;

                $questionData = [
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
                        'parent_field' => $rule->parent_field,
                        'comparison_type' => $rule->comparison_type,
                        'trigger_value' => $rule->trigger_value,
                        'action' => $rule->action,
                        'logical_operator' => $rule->logical_operator ?? 'and',
                    ])->values()->toArray(),
                ];

                // Include file metadata for file-type questions
                if ($question->type === 'file' && isset($answerModels[$question->id])) {
                    $questionData['files'] = $answerModels[$question->id]->files->map(fn ($f) => [
                        'id' => $f->id,
                        'original_filename' => $f->original_filename,
                        'mime_type' => $f->mime_type,
                        'file_size' => $f->file_size,
                        'url' => $f->url,
                        'validation_status' => $f->validation_status,
                        'detected_type' => $f->detected_type,
                        'issue_date' => $f->issue_date,
                        'expiry_date' => $f->expiry_date,
                        'justification' => $f->justification,
                    ])->values()->toArray();
                } elseif ($question->type === 'file') {
                    $questionData['files'] = [];
                }

                // Inject signed URLs into table cells that hold an uploaded file
                if ($question->type === 'table' && ! empty($questionData['answer'])) {
                    $questionData['answer'] = $this->hydrateTableAnswerFileUrls(
                        $questionData['answer'],
                        $question->options['columns'] ?? []
                    );
                }

                return $questionData;
            })->sortBy('order')->values();

            return [
                'id' => $group->id,
                'slug' => $group->slug,
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
     * Accepts JSON or multipart/form-data (when file answers are included).
     */
    public function saveAnswers(SaveAnswersRequest $request): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding) {
            return response()->json(['message' => 'Onboarding not initialized.'], 404);
        }

        if ($this->isSubmitted($onboarding)) {
            return response()->json(['message' => 'Your application has already been submitted and can no longer be edited.'], 403);
        }

        // Save non-file answers
        $textAnswers = $request->validated('answers') ?? [];
        if (!empty($textAnswers)) {
            $this->answerService->saveBulkAnswers(
                $user,
                $onboarding,
                $textAnswers,
            );
        }

        // Save file answers (grouped by question_id)
        $fileAnswers = $request->validated('file_answers') ?? [];
        if (!empty($fileAnswers)) {
            // Group files by question_id
            $grouped = [];
            foreach ($fileAnswers as $entry) {
                $qid = (int) $entry['question_id'];
                $grouped[$qid][] = $entry['file'];
            }

            // AI document validation runs before anything is stored so a
            // rejected batch leaves no partial state. Justifications arrive as
            // file_justifications[question_id].
            $justifications = $request->input('file_justifications', []);
            $validated = [];
            $blockedFailures = [];
            foreach ($grouped as $questionId => $files) {
                $question = Question::find($questionId);
                if (! $question || $question->type !== 'file') {
                    continue;
                }
                $result = $this->documentValidator->validate(
                    $question,
                    $files,
                    $justifications[$questionId] ?? null,
                );
                if ($result['blocked']) {
                    $blockedFailures[$questionId] = $result['failures'];
                } else {
                    $validated[$questionId] = [$question, $files, $result['file_meta']];
                }
            }

            if ($blockedFailures !== []) {
                return response()->json([
                    'message' => 'One or more documents did not pass validation.',
                    'code' => 'document_validation_failed',
                    'document_validation' => $blockedFailures,
                ], 422);
            }

            foreach ($validated as $questionId => [$question, $files, $fileMeta]) {
                $this->answerService->saveFileAnswer(
                    $user,
                    $onboarding,
                    $questionId,
                    $files,
                    null,
                    $fileMeta,
                );
            }
        }

        // Save per-cell files for table-type answers (grouped by question_id)
        $tableFileAnswers = $request->validated('table_file_answers') ?? [];
        if (!empty($tableFileAnswers)) {
            $grouped = [];
            foreach ($tableFileAnswers as $entry) {
                $grouped[(int) $entry['question_id']][] = $entry;
            }

            foreach ($grouped as $questionId => $entries) {
                $question = Question::find($questionId);
                if ($question && $question->type === 'table') {
                    $this->answerService->saveTableCellFiles(
                        $user,
                        $onboarding,
                        $questionId,
                        $entries,
                    );
                }
            }
        }

        return response()->json(['message' => 'Answers saved successfully.']);
    }

    /**
     * Upload file(s) for a file-type question.
     * Accepts multipart/form-data with question_id and files[].
     */
    public function uploadFileAnswer(UploadFileAnswerRequest $request): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding) {
            return response()->json(['message' => 'Onboarding not initialized.'], 404);
        }

        if ($this->isSubmitted($onboarding)) {
            return response()->json(['message' => 'Your application has already been submitted and can no longer be edited.'], 403);
        }

        $question = Question::findOrFail($request->validated('question_id'));

        if ($question->type !== 'file') {
            return response()->json(['message' => 'This question does not accept file uploads.'], 422);
        }

        $result = $this->documentValidator->validate(
            $question,
            $request->file('files'),
            $request->input('justification'),
        );

        if ($result['blocked']) {
            return response()->json([
                'message' => 'One or more documents did not pass validation.',
                'code' => 'document_validation_failed',
                'document_validation' => [$question->id => $result['failures']],
            ], 422);
        }

        $answer = $this->answerService->saveFileAnswer(
            $user,
            $onboarding,
            $question->id,
            $request->file('files'),
            null,
            $result['file_meta'],
        );

        return response()->json([
            'message' => 'File(s) uploaded successfully.',
            'data' => [
                'question_id' => $question->id,
                'answer_id' => $answer->id,
                'files' => $answer->files->map(fn ($f) => [
                    'id' => $f->id,
                    'original_filename' => $f->original_filename,
                    'mime_type' => $f->mime_type,
                    'file_size' => $f->file_size,
                    'url' => $f->url,
                    'validation_status' => $f->validation_status,
                    'detected_type' => $f->detected_type,
                    'justification' => $f->justification,
                ])->values()->toArray(),
            ],
        ]);
    }

    /**
     * Complete the current step and advance to the next.
     */
    public function completeStep(UserOnboardingStep $step): JsonResponse
    {
        /**@disregard */
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
     * Jump directly to an earlier (already-reached) step.
     */
    public function goToStep(UserOnboardingStep $step): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();
        $onboarding = $user->onboarding;

        if (!$onboarding || $step->user_onboarding_id !== $onboarding->id) {
            return response()->json(['message' => 'Step not found.'], 404);
        }

        $onboarding = $this->onboardingService->goToStep($onboarding, $step);

        return response()->json([
            'message' => 'Navigated to step.',
            'data' => $this->formatOnboardingResponse($onboarding),
        ]);
    }

    /**
     * Go back to the previous step.
     */
    public function previousStep(UserOnboardingStep $step): JsonResponse
    {
        /**@disregard */
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

    /**
     * Walk a table-type answer's rows and add a temporary signed `url` to any
     * cell whose column type is `file` and whose value is a stored-file
     * metadata object (has `path` and `disk`).
     *
     * @param  string|array|null  $answer  raw value from UserAnswer (JSON string or already decoded)
     * @param  array<int, array<string, mixed>>  $columns
     * @return string  re-encoded JSON answer with urls injected
     */
    private function hydrateTableAnswerFileUrls(mixed $answer, array $columns): mixed
    {
        $rows = is_string($answer) ? json_decode($answer, true) : $answer;
        if (! is_array($rows)) {
            return $answer;
        }

        $fileColumnKeys = [];
        foreach ($columns as $column) {
            if (($column['type'] ?? null) === 'file' && ! empty($column['key'])) {
                $fileColumnKeys[] = $column['key'];
            }
        }
        if (empty($fileColumnKeys)) {
            return $answer;
        }

        $expiry = now()->addMinutes((int) config('onboarding_uploads.url_expiry_minutes', 60));

        foreach ($rows as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($fileColumnKeys as $key) {
                $cell = $row[$key] ?? null;
                if (! is_array($cell) || empty($cell['path']) || empty($cell['disk'])) {
                    continue;
                }
                try {
                    $disk = Storage::disk($cell['disk']);
                    $url = $cell['disk'] === 's3'
                        ? $disk->temporaryUrl($cell['path'], $expiry)
                        : $disk->url($cell['path']);
                    $rows[$rowIndex][$key]['url'] = $url;
                } catch (\Throwable $e) {
                    // Leave the cell without a url if URL generation fails.
                }
            }
        }

        return is_string($answer) ? json_encode($rows) : $rows;
    }
}
