<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\AnswerAuditLog;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserOnboardingController extends Controller
{
    public function index(Request $request): View
    {
        $query = UserOnboarding::with(['user', 'userType', 'subcategory']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $onboardings = $query->latest()->paginate(20)->withQueryString();

        return view('admin.user-onboardings.index', compact('onboardings'));
    }

    public function show(UserOnboarding $userOnboarding): View
    {
        $userOnboarding->load([
            'user',
            'userType',
            'subcategory',
            'steps',
            'answers.question.group',
        ]);

        return view('admin.user-onboardings.show', compact('userOnboarding'));
    }

    public function toggleStep(UserOnboarding $userOnboarding, UserOnboardingStep $step): RedirectResponse
    {
        if ((int) $step->user_onboarding_id !== (int) $userOnboarding->id) {
            abort(404);
        }

        if ($step->status === 'skipped') {
            $step->update(['status' => 'pending']);
            $message = "Step \"{$step->name}\" has been enabled.";
        } else {
            $step->update(['status' => 'skipped', 'started_at' => null, 'completed_at' => null]);
            $message = "Step \"{$step->name}\" has been disabled (skipped).";
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', $message);
    }

    public function auditLogs(Request $request): View
    {
        $query = AnswerAuditLog::with(['question', 'user', 'editor']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $logs = $query->latest('edited_at')->paginate(20)->withQueryString();

        return view('admin.audit-logs.index', compact('logs'));
    }
}
