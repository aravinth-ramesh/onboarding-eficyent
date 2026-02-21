<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserTypeRequest;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;

class UserTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = UserType::withCount('subcategories')
            ->orderBy('order')
            ->paginate(20);

        return response()->json($types);
    }

    public function store(UserTypeRequest $request): JsonResponse
    {
        $type = UserType::create($request->validated());

        return response()->json([
            'message' => 'User type created.',
            'data' => $type,
        ], 201);
    }

    public function show(UserType $userType): JsonResponse
    {
        $userType->load('subcategories');

        return response()->json(['data' => $userType]);
    }

    public function update(UserTypeRequest $request, UserType $userType): JsonResponse
    {
        $userType->update($request->validated());

        return response()->json([
            'message' => 'User type updated.',
            'data' => $userType,
        ]);
    }

    public function destroy(UserType $userType): JsonResponse
    {
        $userType->delete();

        return response()->json(['message' => 'User type deleted.']);
    }
}
