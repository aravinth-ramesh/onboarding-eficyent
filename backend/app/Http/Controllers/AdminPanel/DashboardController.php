<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users' => User::count(),
            'user_types' => UserType::count(),
            'question_groups' => QuestionGroup::count(),
            'questions' => Question::count(),
            'onboarding_steps' => OnboardingStep::count(),
            'onboardings_total' => UserOnboarding::count(),
            'onboardings_pending' => UserOnboarding::where('status', 'pending')->count(),
            'onboardings_in_progress' => UserOnboarding::where('status', 'in_progress')->count(),
            'onboardings_completed' => UserOnboarding::where('status', 'completed')->count(),
        ];

        $recentOnboardings = UserOnboarding::with(['user', 'userType'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOnboardings'));
    }
}
