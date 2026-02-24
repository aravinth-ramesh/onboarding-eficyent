<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\OnboardingStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingStepController extends Controller
{
    public function index(): View
    {
        $steps = OnboardingStep::orderBy('order')->paginate(20);

        return view('admin.onboarding-steps.index', compact('steps'));
    }

    public function create(): View
    {
        return view('admin.onboarding-steps.form', ['step' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('onboarding_steps')],
            'description' => ['nullable', 'string'],
            'component_key' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'config' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if (!empty($validated['config'])) {
            $validated['config'] = json_decode($validated['config'], true);
        } else {
            $validated['config'] = null;
        }

        DB::transaction(function () use ($validated) {
            OnboardingStep::create($validated);
        });

        return redirect()->route('admin.onboarding-steps.index')
            ->with('success', 'Onboarding step created successfully.');
    }

    public function edit(OnboardingStep $onboardingStep): View
    {
        return view('admin.onboarding-steps.form', ['step' => $onboardingStep]);
    }

    public function update(Request $request, OnboardingStep $onboardingStep): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('onboarding_steps')->ignore($onboardingStep->id)],
            'description' => ['nullable', 'string'],
            'component_key' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'config' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if (!empty($validated['config'])) {
            $validated['config'] = json_decode($validated['config'], true);
        } else {
            $validated['config'] = null;
        }

        $onboardingStep->update($validated);

        return redirect()->route('admin.onboarding-steps.index')
            ->with('success', 'Onboarding step updated successfully.');
    }

    public function destroy(OnboardingStep $onboardingStep): RedirectResponse
    {
        $onboardingStep->delete();

        return redirect()->route('admin.onboarding-steps.index')
            ->with('success', 'Onboarding step deleted successfully.');
    }
}
