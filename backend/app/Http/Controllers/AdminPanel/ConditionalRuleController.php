<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\ConditionalRule;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConditionalRuleController extends Controller
{
    public function index(Request $request): View
    {
        // Resolve trashed questions too. Deleting a question only soft-deletes
        // it, so the DB-level cascade never fires and its rules survive — and
        // because belongsTo applies the soft-delete scope, both sides resolved
        // to null and the row rendered with no detail at all (report item 1).
        $query = ConditionalRule::with([
            'question' => fn ($q) => $q->withTrashed()->with('group'),
            'parentQuestion' => fn ($q) => $q->withTrashed()->with('group'),
        ]);

        if ($request->filled('question_id')) {
            $query->where('question_id', $request->input('question_id'));
        }

        $rules = $query->paginate(20)->withQueryString();
        $questions = Question::with('group')->orderBy('label')->get();

        return view('admin.conditional-rules.index', compact('rules', 'questions'));
    }

    public function create(): View
    {
        $questions = Question::with('group')->orderBy('label')->get();

        return view('admin.conditional-rules.form', [
            'rule' => null,
            'questions' => $questions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ConditionalRule::create($this->ruleData($request));

        return redirect()->route('admin.conditional-rules.index')
            ->with('success', 'Conditional rule created successfully.');
    }

    /**
     * Validate and normalize a rule. The parent may be a question, or the
     * virtual "Country of Incorporation" field (sentinel "__country__").
     */
    private function ruleData(Request $request): array
    {
        $validated = $request->validate([
            'question_id' => ['required', 'exists:questions,id'],
            'parent_question_id' => ['required', 'string'],
            'comparison_type' => ['required', Rule::in([
                'equals', 'not_equals', 'contains', 'not_contains',
                'greater_than', 'less_than', 'in', 'not_in',
                'is_empty', 'is_not_empty',
            ])],
            'trigger_value' => ['nullable', 'string'],
            'action' => [Rule::in(['show', 'hide'])],
            'logical_operator' => [Rule::in(['and', 'or'])],
            'is_active' => ['boolean'],
        ]);

        if ($validated['parent_question_id'] === '__country__') {
            $validated['parent_field'] = 'country_code';
            $validated['parent_question_id'] = null;
        } else {
            $request->validate([
                'parent_question_id' => ['exists:questions,id', 'different:question_id'],
            ]);
            $validated['parent_field'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    public function edit(ConditionalRule $conditionalRule): View
    {
        $conditionalRule->load(['question', 'parentQuestion']);
        $questions = Question::with('group')->orderBy('label')->get();

        return view('admin.conditional-rules.form', [
            'rule' => $conditionalRule,
            'questions' => $questions,
        ]);
    }

    public function update(Request $request, ConditionalRule $conditionalRule): RedirectResponse
    {
        $conditionalRule->update($this->ruleData($request));

        return redirect()->route('admin.conditional-rules.index')
            ->with('success', 'Conditional rule updated successfully.');
    }

    public function destroy(ConditionalRule $conditionalRule): RedirectResponse
    {
        $conditionalRule->delete();

        return redirect()->route('admin.conditional-rules.index')
            ->with('success', 'Conditional rule deleted successfully.');
    }
}
