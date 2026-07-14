<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The client's per-category email opt-outs. Transactional mail (login
 * codes, team invitations) is not configurable.
 */
class NotificationPreferenceController extends Controller
{
    public function show(): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();

        return response()->json(['data' => collect(User::NOTIFICATION_CATEGORIES)
            ->map(fn ($meta, $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'enabled' => $user->wantsEmail($key),
            ])->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.key' => ['required', Rule::in(array_keys(User::NOTIFICATION_CATEGORIES))],
            'preferences.*.enabled' => 'required|boolean',
        ]);

        /**@disregard */
        $user = auth()->user();

        $preferences = $user->notification_preferences ?? [];
        foreach ($validated['preferences'] as $preference) {
            $preferences[$preference['key']] = (bool) $preference['enabled'];
        }

        $user->update(['notification_preferences' => $preferences]);

        return response()->json(['success' => true]);
    }
}
