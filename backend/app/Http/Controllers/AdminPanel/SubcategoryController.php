<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\UserType;
use App\Models\UserTypeSubcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubcategoryController extends Controller
{
    public function index(UserType $userType): View
    {
        $subcategories = $userType->subcategories()
            ->orderBy('order')
            ->paginate(20);

        return view('admin.subcategories.index', compact('userType', 'subcategories'));
    }

    public function create(UserType $userType): View
    {
        return view('admin.subcategories.form', [
            'userType' => $userType,
            'subcategory' => null,
        ]);
    }

    public function store(Request $request, UserType $userType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'order' => ['integer', 'min:0'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $userType->subcategories()->create($validated);

        if (!$userType->has_subcategories) {
            $userType->update(['has_subcategories' => true]);
        }

        return redirect()->route('admin.user-types.subcategories.index', $userType)
            ->with('success', 'Subcategory created successfully.');
    }

    public function edit(UserType $userType, UserTypeSubcategory $subcategory): View
    {
        return view('admin.subcategories.form', compact('userType', 'subcategory'));
    }

    public function update(Request $request, UserType $userType, UserTypeSubcategory $subcategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'order' => ['integer', 'min:0'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $subcategory->update($validated);

        return redirect()->route('admin.user-types.subcategories.index', $userType)
            ->with('success', 'Subcategory updated successfully.');
    }

    public function destroy(UserType $userType, UserTypeSubcategory $subcategory): RedirectResponse
    {
        $subcategory->delete();

        return redirect()->route('admin.user-types.subcategories.index', $userType)
            ->with('success', 'Subcategory deleted successfully.');
    }
}
