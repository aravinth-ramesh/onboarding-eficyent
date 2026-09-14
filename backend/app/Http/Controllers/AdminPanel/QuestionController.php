<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\UserType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Question::with('group');

        if ($request->filled('group_id')) {
            $query->where('question_group_id', $request->input('group_id'));
        }

        $questions = $query->orderBy('order')->paginate(20)->withQueryString();
        $groups = QuestionGroup::orderBy('order')->get();

        return view('admin.questions.index', compact('questions', 'groups'));
    }

    public function create(): View
    {
        $groups = QuestionGroup::orderBy('order')->get();
        $userTypes = UserType::with('subcategories')->orderBy('order')->get();

        return view('admin.questions.form', [
            'question' => null,
            'groups' => $groups,
            'userTypes' => $userTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $validated['is_required'] = $request->boolean('is_required');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['options'] = $this->decodeJsonField($validated['options'] ?? null);
        $validated['validation_rules'] = $this->sanitizeDocumentPolicy(
            $this->decodeJsonField($validated['validation_rules'] ?? null)
        );

        $question = DB::transaction(function () use ($validated) {
            return Question::create($validated);
        });

        $this->syncTypeMappings($request, $question);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Question created successfully.');
    }

    public function edit(Question $question): View
    {
        $question->load(['group', 'typeMappings', 'conditionalRules']);
        $groups = QuestionGroup::orderBy('order')->get();
        $userTypes = UserType::with('subcategories')->orderBy('order')->get();

        return view('admin.questions.form', compact('question', 'groups', 'userTypes'));
    }

    public function update(Request $request, Question $question): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $validated['is_required'] = $request->boolean('is_required');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['options'] = $this->decodeJsonField($validated['options'] ?? null);
        $validated['validation_rules'] = $this->sanitizeDocumentPolicy(
            $this->decodeJsonField($validated['validation_rules'] ?? null)
        );

        $question->update($validated);

        $this->syncTypeMappings($request, $question);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Question updated successfully.');
    }

    /**
     * Shared validation rules for store/update. `options` and
     * `validation_rules` arrive as JSON strings from the form's hidden inputs.
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'question_group_id' => ['required', 'exists:question_groups,id'],
            'label' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            // Keep in sync with Http/Requests/Admin/QuestionRequest and the
            // type dropdown in admin/questions/form.blade.php.
            'type' => ['required', Rule::in(['text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'phone', 'mcc', 'address', 'ubo', 'file', 'table'])],
            'options' => ['nullable', 'string'],
            'validation_rules' => ['nullable', 'string'],
            'is_required' => ['boolean'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * Decode a hidden-field JSON blob — empty string / `{}` / non-json all
     * collapse to `null` so the column stays clean rather than `"[]"`.
     */
    private function decodeJsonField(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || count($decoded) === 0) {
            return null;
        }

        return $decoded;
    }

    /**
     * Keep document-validation policy keys sane: expected_document must name
     * known types from config/document_validation.php, and the age bound is
     * clamped. Unknown selections are dropped rather than rejected so a stale
     * form can't strand the admin.
     */
    private function sanitizeDocumentPolicy(?array $rules): ?array
    {
        if ($rules === null || ! array_key_exists('expected_document', $rules)) {
            return $rules;
        }

        $known = array_keys(config('document_validation.types', []));
        $expected = array_values(array_intersect((array) $rules['expected_document'], $known));

        if ($expected === []) {
            unset($rules['expected_document'], $rules['max_age_months'], $rules['check_expiry']);
        } else {
            $rules['expected_document'] = count($expected) === 1 ? $expected[0] : $expected;
            if (isset($rules['max_age_months'])) {
                $rules['max_age_months'] = max(1, min(120, (int) $rules['max_age_months']));
            }
        }

        return $rules === [] ? null : $rules;
    }

    public function destroy(Question $question): RedirectResponse
    {
        $question->delete();

        return redirect()->route('admin.questions.index')
            ->with('success', 'Question deleted successfully.');
    }

    /**
     * The per-type requiredness override, or null to inherit the question's own
     * flag.
     *
     * The column is nullable precisely so a mapping can defer to the question,
     * and the client reads `$mapping->is_required ?? $question->is_required`.
     * The form always posted a concrete 0 or 1, so the first save replaced
     * "inherit" with a hard false — after which turning the question back to
     * Mandatory changed nothing, because `0 ?? true` is 0 (report item 14).
     *
     * Storing null whenever the two agree keeps the question as the single
     * source of truth, while still allowing a genuine per-type override.
     */
    private static function mappingRequired(array $mapping, Question $question): ?bool
    {
        $required = ! empty($mapping['is_required']);

        return $required === (bool) $question->is_required ? null : $required;
    }

    private function syncTypeMappings(Request $request, Question $question): void
    {
        // An update that carries no mappings at all is not an instruction to
        // unmap the question from every user type — that would remove it from
        // the client form entirely. Only rewrite when the form actually sent
        // the section.
        if (! $request->has('mappings')) {
            return;
        }

        $question->typeMappings()->delete();

        $mappings = $request->input('mappings', []);

        if (! is_array($mappings)) {
            return;
        }

        foreach ($mappings as $mapping) {
            if (empty($mapping['user_type_id'])) {
                continue;
            }

            $subcategoryIds = $mapping['subcategory_ids'] ?? [];

            if (! empty($subcategoryIds) && is_array($subcategoryIds)) {
                foreach ($subcategoryIds as $subId) {
                    $question->typeMappings()->create([
                        'user_type_id' => $mapping['user_type_id'],
                        'user_type_subcategory_id' => $subId,
                        'order' => $question->order,
                        'is_required' => self::mappingRequired($mapping, $question),
                        'is_active' => true,
                    ]);
                }
            } else {
                $question->typeMappings()->create([
                    'user_type_id' => $mapping['user_type_id'],
                    'user_type_subcategory_id' => null,
                    'order' => $question->order,
                    'is_required' => self::mappingRequired($mapping, $question),
                    'is_active' => true,
                ]);
            }
        }
    }
}
