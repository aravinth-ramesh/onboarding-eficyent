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
        $validated = $request->validate([
            'question_group_id' => ['required', 'exists:question_groups,id'],
            'label' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'file'])],
            'options' => ['nullable', 'string'],
            'is_required' => ['boolean'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_required'] = $request->boolean('is_required');
        $validated['is_active'] = $request->boolean('is_active');

        if (!empty($validated['options'])) {
            $validated['options'] = json_decode($validated['options'], true);
        } else {
            $validated['options'] = null;
        }

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
        $validated = $request->validate([
            'question_group_id' => ['required', 'exists:question_groups,id'],
            'label' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'file'])],
            'options' => ['nullable', 'string'],
            'is_required' => ['boolean'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_required'] = $request->boolean('is_required');
        $validated['is_active'] = $request->boolean('is_active');

        if (!empty($validated['options'])) {
            $validated['options'] = json_decode($validated['options'], true);
        } else {
            $validated['options'] = null;
        }

        $question->update($validated);

        $this->syncTypeMappings($request, $question);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Question updated successfully.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $question->delete();

        return redirect()->route('admin.questions.index')
            ->with('success', 'Question deleted successfully.');
    }

    private function syncTypeMappings(Request $request, Question $question): void
    {
        $question->typeMappings()->delete();

        $mappings = $request->input('mappings', []);

        if (!is_array($mappings)) {
            return;
        }

        foreach ($mappings as $mapping) {
            if (empty($mapping['user_type_id'])) {
                continue;
            }

            $subcategoryIds = $mapping['subcategory_ids'] ?? [];

            if (!empty($subcategoryIds) && is_array($subcategoryIds)) {
                foreach ($subcategoryIds as $subId) {
                    $question->typeMappings()->create([
                        'user_type_id' => $mapping['user_type_id'],
                        'user_type_subcategory_id' => $subId,
                        'order' => $question->order,
                        'is_required' => !empty($mapping['is_required']),
                        'is_active' => true,
                    ]);
                }
            } else {
                $question->typeMappings()->create([
                    'user_type_id' => $mapping['user_type_id'],
                    'user_type_subcategory_id' => null,
                    'order' => $question->order,
                    'is_required' => !empty($mapping['is_required']),
                    'is_active' => true,
                ]);
            }
        }
    }
}
