<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AdminQuestion;
use App\Models\AnswerAuditLog;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use App\Services\AdminEmailService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserOnboardingController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private AdminEmailService $emailService,
        private \App\Services\OnboardingService $onboardingService,
    ) {}

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
            'answers.files',
            'reviewLogs.admin',
        ]);

        $userOnboarding->answers->loadCount('auditLogs');

        // Load admin notifications for this user
        $notifications = AdminNotification::where('user_id', $userOnboarding->user_id)
            ->with(['admin', 'userAnswer.question', 'adminQuestion.answer'])
            ->orderByDesc('created_at')
            ->get();

        // Load admin questions assigned to this user
        $adminQuestions = AdminQuestion::where('user_id', $userOnboarding->user_id)
            ->with(['admin', 'answer', 'notification'])
            ->orderByDesc('created_at')
            ->get();

        // Map answer IDs that have pending change requests
        $pendingChangeRequestAnswerIds = $notifications
            ->where('type', 'change_request')
            ->where('status', 'pending')
            ->pluck('user_answer_id')
            ->filter()
            ->toArray();

        return view('admin.user-onboardings.show', compact(
            'userOnboarding',
            'notifications',
            'adminQuestions',
            'pendingChangeRequestAnswerIds',
        ));
    }

    public function answerHistory(UserOnboarding $userOnboarding, UserAnswer $answer): View
    {
        if ((int) $answer->user_onboarding_id !== (int) $userOnboarding->id) {
            abort(404);
        }

        $answer->load(['question.group', 'files']);

        $logs = AnswerAuditLog::where('user_answer_id', $answer->id)
            ->with(['editor'])
            ->latest('edited_at')
            ->paginate(20);

        return view('admin.user-onboardings.answer-history', compact('userOnboarding', 'answer', 'logs'));
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

    public function approve(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        $validated = $request->validate(['comment' => 'nullable|string|max:2000']);

        try {
            $this->onboardingService->approve(
                $userOnboarding,
                Auth::guard('admin')->user(),
                $validated['comment'] ?? null,
            );
        } catch (\DomainException $e) {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Application approved — the client has been notified by email.');
    }

    public function reject(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        $validated = $request->validate(['comment' => 'required|string|max:2000']);

        try {
            $this->onboardingService->reject(
                $userOnboarding,
                Auth::guard('admin')->user(),
                $validated['comment'],
            );
        } catch (\DomainException $e) {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Application rejected — the client has been notified by email.');
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

    public function requestChange(Request $request, UserOnboarding $userOnboarding, UserAnswer $answer): RedirectResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        if ((int) $answer->user_onboarding_id !== (int) $userOnboarding->id) {
            abort(404);
        }

        $admin = Auth::guard('admin')->user();
        $notification = $this->notificationService->createChangeRequest($admin, $answer, $request->input('message'));

        // An email notification always accompanies a change request; the form
        // may override the default subject/body. A mail failure must not undo
        // the change request itself.
        $user = $userOnboarding->user;
        $questionLabel = $answer->question->label ?? '';
        $subject = $request->input('email_subject') ?: $this->emailService->getDefaultSubject('change_request', $questionLabel);
        $body = $request->input('email_body') ?: $this->emailService->getDefaultBody('change_request', [
            'user_name' => $user->name ?? 'there',
            'question_label' => $questionLabel,
        ]);

        try {
            $this->emailService->sendEmail($admin, $user, $subject, $body, $notification);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', 'Change request created, but the email notification could not be sent.');
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Change request sent to user and email notification delivered.');
    }

    public function createQuestion(UserOnboarding $userOnboarding): View
    {
        $userOnboarding->load(['user']);

        return view('admin.user-onboardings.new-question', compact('userOnboarding'));
    }

    public function storeQuestion(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:500',
            'description' => 'nullable|string|max:2000',
            'type' => 'required|in:text,radio,date,select,multi_select,textarea,number,file',
            'options' => 'nullable|json',
            'is_required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'help_text' => 'nullable|string|max:500',
            'message' => 'required|string|max:2000',
        ]);

        $admin = Auth::guard('admin')->user();
        $user = $userOnboarding->user;

        $questionData = [
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'options' => isset($validated['options']) ? json_decode($validated['options'], true) : null,
            'is_required' => $request->boolean('is_required'),
            'placeholder' => $validated['placeholder'] ?? null,
            'help_text' => $validated['help_text'] ?? null,
        ];

        $notification = $this->notificationService->createNewQuestion($admin, $user, $questionData, $validated['message']);

        // Send email if requested
        if ($request->boolean('send_email')) {
            $subject = $request->input('email_subject') ?: $this->emailService->getDefaultSubject('new_question', $validated['label']);
            $body = $request->input('email_body') ?: $this->emailService->getDefaultBody('new_question', [
                'user_name' => $user->name ?? 'there',
                'question_label' => $validated['label'],
            ]);
            $this->emailService->sendEmail($admin, $user, $subject, $body, $notification);
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'New question assigned to user.');
    }

    public function sendEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'notification_id' => 'nullable|exists:admin_notifications,id',
            'subject' => 'required|string|max:500',
            'body' => 'required|string|max:10000',
            'redirect_to' => 'nullable|string',
        ]);

        $admin = Auth::guard('admin')->user();
        $user = \App\Models\User::findOrFail($validated['user_id']);
        $notification = isset($validated['notification_id'])
            ? AdminNotification::find($validated['notification_id'])
            : null;

        $this->emailService->sendEmail($admin, $user, $validated['subject'], $validated['body'], $notification);

        $redirectTo = $validated['redirect_to'] ?? url()->previous();

        return redirect($redirectTo)->with('success', 'Email sent successfully.');
    }
}
