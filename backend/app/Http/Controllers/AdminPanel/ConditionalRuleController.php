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
        $query = ConditionalRule::with(['question', 'parentQuestion']);

        if ($request->filled('question_id')) {
            $query->where('question_id', $request->input('question_id'));
        }

        $rules = $query->paginate(20)->withQueryString();
        $questions = Question::orderBy('label')->get();

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
        $validated = $request->validate([
            'question_id' => ['required', 'exists:questions,id'],
            'parent_question_id' => ['required', 'exists:questions,id', 'different:question_id'],
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

        $validated['is_active'] = $request->boolean('is_active');

        ConditionalRule::create($validated);

        return redirect()->route('admin.conditional-rules.index')
            ->with('success', 'Conditional rule created successfully.');
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
        $validated = $request->validate([
            'question_id' => ['required', 'exists:questions,id'],
            'parent_question_id' => ['required', 'exists:questions,id', 'different:question_id'],
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

        $validated['is_active'] = $request->boolean('is_active');

        $conditionalRule->update($validated);

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
