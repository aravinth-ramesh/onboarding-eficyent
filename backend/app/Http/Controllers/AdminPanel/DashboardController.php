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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        // Analysts don't get the global platform dashboard — their home is the
        // queue of companies assigned to them.
        if ($admin->seesOnlyAssignedOnboardings()) {
            return redirect()->route('admin.user-onboardings.index');
        }

        $stats = [
            // Clients who actually hold an application, which is what the
            // Onboardings module lists. Counting every user row also swept in
            // invited team members and invitation-only accounts, so the two
            // screens reported different totals (retest item 37).
            'users' => User::whereHas('onboarding')->count(),
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
            'workload' => $admin->hasAbility(\App\Enums\Ability::VIEW_WORKLOAD)
                ? $this->teamWorkload()
                : collect(),
            'unassignedOpen' => UserOnboarding::where('status', 'completed')->whereNull('assigned_to')->count(),
            // Four-eyes checker queue: applications handed off for a decision,
            // excluding any this admin submitted themselves.
            'approvalQueue' => $admin->hasAbility(\App\Enums\Ability::APPROVE_ONBOARDING)
                ? UserOnboarding::awaitingApprovalBy($admin)
                    ->with(['user', 'submittedForApprovalBy'])
                    ->orderByRaw("approval_state = 'escalated' desc")
                    ->orderBy('submitted_for_approval_at')
                    ->limit(10)->get()
                : collect(),
            'approvalQueueTotal' => $admin->hasAbility(\App\Enums\Ability::APPROVE_ONBOARDING)
                ? UserOnboarding::awaitingApprovalBy($admin)->count()
                : 0,
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
        // The review log is append-only: a reject, reopen, resubmit and
        // approve leaves two rows for one application, so counting events
        // showed the same application under both Approved and Rejected
        // (report item 13). Reduce to the latest decision per application, so
        // the workload reflects where each application actually stands.
        // De-duplicated in memory rather than with a window function because
        // the 30-day set is small and the suite runs on SQLite.
        $decisions = OnboardingReviewLog::whereIn('event', ['approved', 'rejected'])
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('admin_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['admin_id', 'event', 'user_onboarding_id', 'created_at'])
            ->unique('user_onboarding_id')
            ->groupBy('admin_id')
            ->map(fn ($rows) => $rows->countBy('event'));

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
                    'approved_30d' => (int) ($own['approved'] ?? 0),
                    'rejected_30d' => (int) ($own['rejected'] ?? 0),
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
