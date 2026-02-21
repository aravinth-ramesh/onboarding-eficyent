<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;

class UserTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = UserType::where('is_active', true)
            ->orderBy('order')
            ->with(['subcategories' => function ($query) {
                $query->where('is_active', true)->orderBy('order');
            }])
            ->get()
            ->map(fn (UserType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'description' => $type->description,
                'has_subcategories' => $type->has_subcategories,
                'subcategories' => $type->has_subcategories
                    ? $type->subcategories->map(fn ($sub) => [
                        'id' => $sub->id,
                        'name' => $sub->name,
                        'slug' => $sub->slug,
                        'description' => $sub->description,
                    ])
                    : [],
            ]);

        return response()->json(['data' => $types]);
    }
}
