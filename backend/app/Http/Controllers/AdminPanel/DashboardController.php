<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\OnboardingReviewLog;
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
            'onboardings_approved' => UserOnboarding::where('status', 'approved')->count(),
            'onboardings_rejected' => UserOnboarding::where('status', 'rejected')->count(),
        ];

        $recentOnboardings = UserOnboarding::with(['user', 'userType'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentOnboardings' => $recentOnboardings,
            'decisionStats' => $this->decisionStats(),
            'recentDecisions' => OnboardingReviewLog::with(['admin', 'onboarding.user'])
                ->whereIn('event', ['approved', 'rejected'])
                ->latest('created_at')->latest('id')
                ->limit(8)
                ->get(),
            'workload' => $this->teamWorkload(),
            'unassignedOpen' => UserOnboarding::where('status', 'completed')->whereNull('assigned_to')->count(),
            // Client responses no admin has acknowledged yet — a real work
            // queue: entries leave once someone marks them checked.
            'clientResponses' => \App\Models\AdminNotification::with(['user.onboarding', 'userAnswer.question', 'adminQuestion'])
                ->awaitingCheck()
                ->latest('resolved_at')
                ->limit(10)
                ->get(),
            'clientResponsesTotal' => \App\Models\AdminNotification::awaitingCheck()->count(),
        ]);
    }

    /**
     * Per-admin view of the review queue: open assignments right now, and
     * decisions made in the last 30 days (from the immutable review log).
     */
    private function teamWorkload()
    {
        $decisions = OnboardingReviewLog::whereIn('event', ['approved', 'rejected'])
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('admin_id')
            ->selectRaw('admin_id, event, count(*) as total')
            ->groupBy('admin_id', 'event')
            ->get()
            ->groupBy('admin_id');

        return \App\Models\Admin::where('is_active', true)
            ->withCount(['assignedOnboardings as open_count' => fn ($q) => $q->where('status', 'completed')])
            ->orderByDesc('open_count')
            ->orderBy('name')
            ->get()
            ->map(function ($admin) use ($decisions) {
                $own = $decisions->get($admin->id, collect());

                return (object) [
                    'admin' => $admin,
                    'open' => $admin->open_count,
                    'approved_30d' => (int) $own->firstWhere('event', 'approved')?->total,
                    'rejected_30d' => (int) $own->firstWhere('event', 'rejected')?->total,
                ];
            });
    }

    /**
     * Review activity over the last 30 days, including the average time from
     * submission (or resubmission) to the decision.
     */
    private function decisionStats(): array
    {
        $since = now()->subDays(30);

        $decisions = OnboardingReviewLog::whereIn('event', ['approved', 'rejected'])
            ->where('created_at', '>=', $since)
            ->get();

        $decisionHours = app(\App\Services\ReviewTimeEstimator::class)->pairedDecisionHours($since);

        return [
            'approved_30d' => $decisions->where('event', 'approved')->count(),
            'rejected_30d' => $decisions->where('event', 'rejected')->count(),
            'resubmissions_30d' => OnboardingReviewLog::where('event', 'resubmitted')
                ->where('created_at', '>=', $since)
                ->count(),
            'avg_decision_hours' => $decisionHours->isEmpty()
                ? null
                : round($decisionHours->avg(), 1),
        ];
    }
}
