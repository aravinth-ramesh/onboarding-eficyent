<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\QuestionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuestionGroupController extends Controller
{
    public function index(): View
    {
        $groups = QuestionGroup::withCount('questions')
            ->orderBy('order')
            ->paginate(20);

        return view('admin.question-groups.index', compact('groups'));
    }

    public function create(): View
    {
        return view('admin.question-groups.form', ['group' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('question_groups')],
            'description' => ['nullable', 'string'],
            'order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        QuestionGroup::create($validated);

        return redirect()->route('admin.question-groups.index')
            ->with('success', 'Question group created successfully.');
    }

    public function edit(QuestionGroup $questionGroup): View
    {
        $questionGroup->load('questions');

        return view('admin.question-groups.form', ['group' => $questionGroup]);
    }

    public function update(Request $request, QuestionGroup $questionGroup): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('question_groups')->ignore($questionGroup->id)],
            'description' => ['nullable', 'string'],
            'order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $questionGroup->update($validated);

        return redirect()->route('admin.question-groups.index')
            ->with('success', 'Question group updated successfully.');
    }

    public function destroy(QuestionGroup $questionGroup): RedirectResponse
    {
        $questionGroup->delete();

        return redirect()->route('admin.question-groups.index')
            ->with('success', 'Question group deleted successfully.');
    }
}
