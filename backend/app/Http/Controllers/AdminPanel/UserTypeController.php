<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\UserType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserTypeController extends Controller
{
    public function index(): View
    {
        $userTypes = UserType::withCount('subcategories')
            ->orderBy('order')
            ->paginate(20);

        return view('admin.user-types.index', compact('userTypes'));
    }

    public function create(): View
    {
        return view('admin.user-types.form', ['userType' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('user_types')],
            'description' => ['nullable', 'string'],
            'has_subcategories' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $validated['has_subcategories'] = $request->boolean('has_subcategories');
        $validated['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use ($validated) {
            UserType::create($validated);
        });

        return redirect()->route('admin.user-types.index')
            ->with('success', 'User type created successfully.');
    }

    public function edit(UserType $userType): View
    {
        $userType->load('subcategories');

        return view('admin.user-types.form', compact('userType'));
    }

    public function update(Request $request, UserType $userType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('user_types')->ignore($userType->id)],
            'description' => ['nullable', 'string'],
            'has_subcategories' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $validated['has_subcategories'] = $request->boolean('has_subcategories');
        $validated['is_active'] = $request->boolean('is_active');

        $userType->update($validated);

        return redirect()->route('admin.user-types.index')
            ->with('success', 'User type updated successfully.');
    }

    public function destroy(UserType $userType): RedirectResponse
    {
        $userType->delete();

        return redirect()->route('admin.user-types.index')
            ->with('success', 'User type deleted successfully.');
    }
}
