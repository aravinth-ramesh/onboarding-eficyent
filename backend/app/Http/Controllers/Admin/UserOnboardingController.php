<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnswerAuditLog;
use App\Models\User;
use App\Models\UserOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserOnboardingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = UserOnboarding::with(['user', 'userType', 'subcategory']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $onboardings = $query->latest()->paginate(20);

        return response()->json($onboardings);
    }

    public function show(UserOnboarding $userOnboarding): JsonResponse
    {
        $userOnboarding->load([
            'user',
            'userType',
            'subcategory',
            'steps',
            'answers.question.group',
        ]);

        return response()->json(['data' => $userOnboarding]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $query = AnswerAuditLog::with(['question', 'user', 'editor']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $logs = $query->latest('edited_at')->paginate(20);

        return response()->json($logs);
    }

    /**
     * Admin can customize a user's onboarding step order.
     */
    public function reorderSteps(UserOnboarding $userOnboarding): JsonResponse
    {
        $validated = request()->validate([
            'steps' => ['required', 'array'],
            'steps.*.id' => ['required', 'exists:user_onboarding_steps,id'],
            'steps.*.order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['steps'] as $stepData) {
            $userOnboarding->steps()
                ->where('id', $stepData['id'])
                ->update(['order' => $stepData['order']]);
        }

        return response()->json(['message' => 'User onboarding steps reordered.']);
    }
}
