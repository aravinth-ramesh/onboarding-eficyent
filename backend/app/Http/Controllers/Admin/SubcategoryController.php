<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubcategoryRequest;
use App\Models\UserType;
use App\Models\UserTypeSubcategory;
use Illuminate\Http\JsonResponse;

class SubcategoryController extends Controller
{
    public function index(UserType $userType): JsonResponse
    {
        $subcategories = $userType->subcategories()
            ->orderBy('order')
            ->paginate(20);

        return response()->json($subcategories);
    }

    public function store(SubcategoryRequest $request, UserType $userType): JsonResponse
    {
        $subcategory = $userType->subcategories()->create($request->validated());

        // Ensure parent has_subcategories flag is set
        if (!$userType->has_subcategories) {
            $userType->update(['has_subcategories' => true]);
        }

        return response()->json([
            'message' => 'Subcategory created.',
            'data' => $subcategory,
        ], 201);
    }

    public function show(UserType $userType, UserTypeSubcategory $subcategory): JsonResponse
    {
        return response()->json(['data' => $subcategory]);
    }

    public function update(SubcategoryRequest $request, UserType $userType, UserTypeSubcategory $subcategory): JsonResponse
    {
        $subcategory->update($request->validated());

        return response()->json([
            'message' => 'Subcategory updated.',
            'data' => $subcategory,
        ]);
    }

    public function destroy(UserType $userType, UserTypeSubcategory $subcategory): JsonResponse
    {
        $subcategory->delete();

        return response()->json(['message' => 'Subcategory deleted.']);
    }
}
