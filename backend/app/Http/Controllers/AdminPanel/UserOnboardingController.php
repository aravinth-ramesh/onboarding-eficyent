<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Concerns\ParsesDateRange;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AdminQuestion;
use App\Models\AnswerAuditLog;
use App\Models\AnswerFile;
use App\Models\FilterPreset;
use App\Models\OnboardingSectionReview;
use App\Models\QuestionGroup;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use App\Models\UserType;
use App\Services\AdminEmailService;
use App\Services\NotificationService;
use App\Support\AnswerValueFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserOnboardingController extends Controller
{
    use ParsesDateRange;

    /** This page's key in FilterPreset::CONTEXTS. */
    private const CONTEXT = 'user-onboardings';

    /** Query params that make up the admin's active view. */
    private const FILTER_KEYS = FilterPreset::CONTEXTS[self::CONTEXT];

    /**
     * Date columns an admin can range on, keyed by the `date_field` param.
     * An application has several meaningful dates, so the range filter says
     * which one it means rather than guessing.
     */
    public const DATE_FIELDS = [
        'submitted' => 'completed_at',
        'started' => 'started_at',
        'decided' => 'decided_at',
    ];

    public function __construct(
        private NotificationService $notificationService,
        private AdminEmailService $emailService,
        private \App\Services\OnboardingService $onboardingService,
    ) {}

    public function index(Request $request): View
    {
        $onboardings = $this->filteredQuery($request)->with('assignee')->latest()->paginate(20)->withQueryString();
        $userTypes = UserType::orderBy('order')->get();
        $admins = Admin::reviewers()->get();

        // Saved views for whoever is looking, and which one (if any) the
        // current filters match, so it can be shown as selected.
        $presets = FilterPreset::ownedBy(Auth::guard('admin')->id(), self::CONTEXT)->get();
        $active = FilterPreset::normalize(self::CONTEXT, collect($request->only(self::FILTER_KEYS))
            ->filter(fn ($v) => filled($v))
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->all());

        return view('admin.user-onboardings.index', [
            ...compact('onboardings', 'userTypes', 'admins', 'presets'),
            'activePresetId' => $presets->first(fn ($p) => $p->filters == $active)?->id,
            'presetSummary' => $this->describeFilters($active, $userTypes, $admins),
        ]);
    }

    /**
     * Human-readable "label => value" of the active filters, for the save
     * preset dialog — ids resolved to the names the admin actually picked.
     */
    private function describeFilters(array $filters, $userTypes, $admins): array
    {
        $statuses = [
            'pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Awaiting Review',
            'approved' => 'Approved', 'rejected' => 'Rejected',
        ];

        $assignee = match ($filters['assigned'] ?? null) {
            null => null,
            'me' => 'Assigned to me',
            'unassigned' => 'Unassigned',
            default => $admins->firstWhere('id', (int) $filters['assigned'])?->name ?? 'Unknown admin',
        };

        $range = collect([
            isset($filters['from']) ? "from {$filters['from']}" : null,
            isset($filters['to']) ? "to {$filters['to']}" : null,
        ])->filter()->implode(' ');

        return collect([
            'Search' => $filters['search'] ?? null,
            'Status' => $statuses[$filters['status'] ?? ''] ?? null,
            'Type' => isset($filters['user_type_id'])
                ? $userTypes->firstWhere('id', (int) $filters['user_type_id'])?->name
                : null,
            'Assignee' => $assignee,
            'Resubmissions only' => isset($filters['resubmitted']) ? 'yes' : null,
            'Archived' => isset($filters['archived']) ? 'yes' : null,
            ucfirst($filters['date_field'] ?? 'submitted') => $range ?: null,
        ])->filter()->all();
    }

    /**
     * Stream the (filtered) list as CSV — same filters as the index, so
     * "what you see is what you export".
     */
    public function exportCsv(Request $request)
    {
        $filename = 'onboardings-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Reference', 'Company', 'Contact Name', 'Email', 'Organization Type', 'Subcategory',
                'Status', 'Approval Stage', 'Submitted For Approval By',
                'Sections Reviewed', 'Days Waiting', 'SLA',
                'Resubmission', 'Country', 'Assigned To', 'Started', 'Submitted',
                'Decided', 'Decided By', 'Decision Comment',
            ]);

            $approvalLabels = ['pending_approval' => 'Awaiting approval', 'escalated' => 'Escalated'];

            $this->filteredQuery($request)
                ->with(['decidedBy', 'assignee', 'submittedForApprovalBy'])
                ->withCount(['sectionReviews as sections_reviewed_count' => fn ($q) => $q->where('status', 'completed')])
                ->latest()
                ->lazy()
                ->each(function (UserOnboarding $o) use ($out, $approvalLabels) {
                    $aging = $o->reviewAging();
                    fputcsv($out, [
                        $o->reference,
                        $o->company_name ?? '',
                        $o->user->name ?? '',
                        $o->user->email ?? '',
                        $o->userType->name ?? '',
                        $o->subcategory->name ?? '',
                        $o->status,
                        $o->approval_state ? ($approvalLabels[$o->approval_state] ?? $o->approval_state) : '',
                        $o->submittedForApprovalBy->name ?? '',
                        $o->sections_reviewed_count,
                        $aging['days'] ?? '',
                        $aging ? ($aging['overdue'] ? 'overdue' : 'on track') : '',
                        $o->reopened_at ? 'yes' : 'no',
                        $o->country_code ?? '',
                        $o->assignee->name ?? '',
                        $o->started_at?->toDateTimeString() ?? '',
                        $o->completed_at?->toDateTimeString() ?? '',
                        $o->decided_at?->toDateTimeString() ?? '',
                        $o->decidedBy->name ?? '',
                        $o->decision_comment ?? '',
                    ]);
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Decide several applications at once. Each eligible application goes
     * through the same service path as a single decision — per-client email,
     * review-log entry, transition guard — so bulk is just a loop, not a
     * shortcut around the lifecycle. Ineligible rows are skipped and counted.
     */
    /**
     * Send one composed email to several clients at once. A deliberate
     * broadcast from an admin — so it bypasses per-category notification
     * prefs (like the single Send Email action) but skips clients with no
     * email. Each send is logged via AdminEmailService.
     */
    public function bulkEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:user_onboardings,id',
            'subject' => 'required|string|max:500',
            'body' => 'required|string|max:10000',
            'send_at' => 'nullable|date|after:now',
        ]);

        $admin = Auth::guard('admin')->user();
        $redirect = redirect()->route('admin.user-onboardings.index', $request->except(['ids', 'subject', 'body', 'send_at', '_token']));

        // Scheduled: snapshot the recipients now, send later via the command.
        if (! empty($validated['send_at'])) {
            $scheduled = \App\Models\ScheduledEmail::create([
                'admin_id' => $admin->id,
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'onboarding_ids' => array_values($validated['ids']),
                'send_at' => $validated['send_at'],
                'status' => 'pending',
            ]);

            return $redirect->with('success',
                "Email scheduled for {$scheduled->send_at->format('M d, Y H:i')} to ".count($validated['ids']).' client(s).');
        }

        $sent = $this->emailService->sendBulk($admin, $validated['ids'], $validated['subject'], $validated['body']);
        $skipped = count($validated['ids']) - $sent;

        $message = "Email queued to {$sent} client(s)."
            .($skipped > 0 ? " {$skipped} skipped (no email address or send failed)." : '');

        return $redirect->with($sent > 0 ? 'success' : 'error', $message);
    }

    public function bulkDecision(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:user_onboardings,id',
            'comment' => 'required_if:decision,reject|nullable|string|max:2000',
        ]);

        $admin = Auth::guard('admin')->user();
        $decided = 0;
        $skipped = 0;

        // Scope to the caller's visible rows — mirrors bulkAssign so the guard
        // doesn't rely on the route's role gating alone.
        $onboardings = UserOnboarding::visibleTo($admin)->whereIn('id', $validated['ids'])->get();

        foreach ($onboardings as $onboarding) {
            try {
                $validated['decision'] === 'approve'
                    ? $this->onboardingService->approve($onboarding, $admin, $validated['comment'] ?? null)
                    : $this->onboardingService->reject($onboarding, $admin, $validated['comment']);
                $decided++;
            } catch (\DomainException) {
                // Not awaiting review, the caller submitted it themselves
                // (four-eyes), or its sections aren't fully reviewed.
                $skipped++;
            }
        }

        $verb = $validated['decision'] === 'approve' ? 'approved' : 'rejected';
        $message = "{$decided} application(s) {$verb}."
            .($skipped > 0 ? " {$skipped} skipped (couldn't be {$verb} — already decided, awaiting a second reviewer, or sections not fully reviewed)." : '');

        return redirect()->route('admin.user-onboardings.index', $request->except(['ids', 'decision', 'comment', '_token']))
            ->with($decided > 0 ? 'success' : 'error', $message);
    }

    private function filteredQuery(Request $request)
    {
        // Analysts only ever see their own assignments; other roles see all.
        $query = UserOnboarding::with(['user', 'userType', 'subcategory'])
            ->visibleTo(Auth::guard('admin')->user());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user_type_id')) {
            $query->where('user_type_id', $request->input('user_type_id'));
        }

        if ($request->boolean('resubmitted')) {
            $query->whereNotNull('reopened_at');
        }

        // Archived applications are hidden unless explicitly requested.
        $request->boolean('archived')
            ? $query->whereNotNull('archived_at')
            : $query->whereNull('archived_at');

        if ($request->filled('assigned')) {
            $assigned = $request->input('assigned');
            match (true) {
                $assigned === 'unassigned' => $query->whereNull('assigned_to'),
                $assigned === 'me' => $query->where('assigned_to', Auth::guard('admin')->id()),
                default => $query->where('assigned_to', (int) $assigned),
            };
        }

        // Range on whichever date the admin picked (default: submitted).
        // Rows with no date in that column drop out, which is the point — an
        // application submitted between two dates must have been submitted.
        $column = self::DATE_FIELDS[(string) $request->input('date_field')] ?? self::DATE_FIELDS['submitted'];

        if ($from = $this->parseDate($request->input('from'))) {
            $query->where($column, '>=', $from->startOfDay());
        }

        if ($to = $this->parseDate($request->input('to'))) {
            $query->where($column, '<=', $to->endOfDay());
        }

        if ($request->filled('search')) {
            $term = trim($request->input('search'));

            // "ONB-2026-0042", "onb-42" or a bare number targets the reference.
            $referenceId = preg_match('/^(?:ONB-?)(?:\d{4}-?)?0*(\d+)$/i', $term, $m)
                ? (int) $m[1]
                : (ctype_digit($term) ? (int) ltrim($term, '0') : null);

            $query->where(function ($q) use ($term, $referenceId) {
                $q->where('company_name', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($u) use ($term) {
                        $u->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
                if ($referenceId !== null) {
                    $q->orWhere('id', $referenceId);
                }
            });
        }

        // Four-eyes checker/compliance queues: applications handed off for a
        // decision, or escalated to compliance.
        if (in_array($request->input('approval'), ['pending_approval', 'escalated'], true)) {
            $query->where('approval_state', $request->input('approval'));
        }

        return $query;
    }

    public function show(UserOnboarding $userOnboarding): View
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        $userOnboarding->load([
            'user',
            'userType',
            'subcategory',
            'steps',
            'answers.question.group',
            'answers.files',
            'reviewLogs.admin',
            'notes.admin',
            'messages.admin',
            'assignee',
            'answers.files.reviewer',
            'sectionReviews',
        ]);

        // Viewing the thread counts as reading the client's messages.
        $userOnboarding->messages()
            ->where('sender_type', 'client')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $userOnboarding->answers->loadCount('auditLogs');

        // Load admin notifications for this user
        $notifications = AdminNotification::where('user_id', $userOnboarding->user_id)
            ->with(['admin', 'userAnswer.question.group', 'adminQuestion.answer', 'adminQuestion.group'])
            ->orderByDesc('created_at')
            ->get();

        // Load admin questions assigned to this user
        $adminQuestions = AdminQuestion::where('user_id', $userOnboarding->user_id)
            ->with(['admin', 'answer.files', 'notification'])
            ->orderByDesc('created_at')
            ->get();

        // Map answer IDs that have pending change requests
        $pendingChangeRequestAnswerIds = $notifications
            ->where('type', 'change_request')
            ->where('status', 'pending')
            ->pluck('user_answer_id')
            ->filter()
            ->toArray();

        $admins = Admin::reviewers()->get();

        return view('admin.user-onboardings.show', compact(
            'userOnboarding',
            'notifications',
            'adminQuestions',
            'pendingChangeRequestAnswerIds',
            'admins',
        ));
    }

    public function answerHistory(UserOnboarding $userOnboarding, UserAnswer $answer): View
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        if ((int) $answer->user_onboarding_id !== (int) $userOnboarding->id) {
            abort(404);
        }

        $answer->load(['question.group', 'files']);

        // Opening the edit history IS the admin reviewing the client's update,
        // so any resolved change request for this answer is auto-marked checked
        // — no separate "Checked" click needed (EOP-76).
        AdminNotification::where('user_answer_id', $answer->id)
            ->where('type', 'change_request')
            ->where('status', 'resolved')
            ->whereNull('checked_at')
            ->update(['checked_at' => now(), 'checked_by' => Auth::guard('admin')->id()]);

        $logs = AnswerAuditLog::where('user_answer_id', $answer->id)
            ->with(['editor', 'question'])
            ->latest('edited_at')
            ->paginate(20);

        return view('admin.user-onboardings.answer-history', compact('userOnboarding', 'answer', 'logs'));
    }

    /**
     * Record where a reviewer has reached on a section (QuestionGroup) of this
     * application, so a long review can be paused and resumed. Only marks
     * belonging to sections the application actually contains are accepted.
     */
    public function reviewSection(Request $request, UserOnboarding $userOnboarding, QuestionGroup $group): RedirectResponse|JsonResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        // The section must be one the client actually answered — no marking
        // progress on groups that aren't part of this application.
        $belongs = $userOnboarding->answers()
            ->whereHas('question', fn ($q) => $q->where('question_group_id', $group->id))
            ->exists();
        abort_unless($belongs, 404);

        $completed = $validated['status'] === 'completed';

        // A reviewed section is a sign-off, not a toggle: it can't be quietly
        // walked back to "In review"/"Not started", which would also silently
        // decrement the approval progress gate (EOP-74). It reopens only
        // through a change request or a full application reopen.
        $existingReview = OnboardingSectionReview::where('user_onboarding_id', $userOnboarding->id)
            ->where('question_group_id', $group->id)
            ->first();

        if ($existingReview && $existingReview->status === 'completed' && ! $completed) {
            $error = 'This section is already signed off as reviewed. Request a change on one of its answers to reopen it.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $error], 422);
            }

            return redirect()
                ->to(route('admin.user-onboardings.show', $userOnboarding).'#section-'.$group->id)
                ->with('error', $error);
        }

        // A section with an outstanding change request isn't done — the client
        // still has to resubmit it. Don't let it be marked reviewed (EOP-71).
        if ($completed) {
            $hasPendingChange = AdminNotification::where('type', 'change_request')
                ->where('status', 'pending')
                ->whereHas('userAnswer', fn ($q) => $q
                    ->where('user_onboarding_id', $userOnboarding->id)
                    ->whereHas('question', fn ($qq) => $qq->where('question_group_id', $group->id)))
                ->exists();

            if ($hasPendingChange) {
                $error = 'This section has a pending change request — it can be marked reviewed once the client resubmits.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $error], 422);
                }

                return redirect()
                    ->to(route('admin.user-onboardings.show', $userOnboarding).'#section-'.$group->id)
                    ->with('error', $error);
            }
        }

        OnboardingSectionReview::updateOrCreate(
            ['user_onboarding_id' => $userOnboarding->id, 'question_group_id' => $group->id],
            [
                'status' => $validated['status'],
                'note' => $validated['note'] ?? null,
                'reviewed_by' => Auth::guard('admin')->id(),
                'reviewed_at' => $completed ? now() : null,
            ],
        );

        // Marking the section reviewed also acknowledges any resolved change
        // requests within it — no separate "Checked" click required (EOP-76).
        if ($completed) {
            AdminNotification::where('type', 'change_request')
                ->where('status', 'resolved')
                ->whereNull('checked_at')
                ->whereHas('userAnswer', fn ($q) => $q
                    ->where('user_onboarding_id', $userOnboarding->id)
                    ->whereHas('question', fn ($qq) => $qq->where('question_group_id', $group->id)))
                ->update(['checked_at' => now(), 'checked_by' => Auth::guard('admin')->id()]);
        }

        $message = $completed ? 'Section marked as reviewed.' : 'Section progress saved.';

        // AJAX save updates the section in place — no full reload that would
        // flash to the top of the page and scroll back down (EOP-68).
        if ($request->expectsJson()) {
            $userOnboarding->load('sectionReviews');

            return response()->json([
                'status' => $validated['status'],
                'message' => $message,
                'reviewed_at' => $completed ? now()->diffForHumans() : null,
                'reviewer' => Auth::guard('admin')->user()->name,
                'progress' => $userOnboarding->sectionReviewProgress(),
            ]);
        }

        return redirect()
            ->to(route('admin.user-onboardings.show', $userOnboarding).'#section-'.$group->id)
            ->with('success', $message);
    }

    /**
     * A reviewer's verdict on a single uploaded document — verified, rejected,
     * or a resubmission requested — distinct from the automated validation.
     */
    public function reviewDocument(Request $request, UserOnboarding $userOnboarding, AnswerFile $file): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        // The file must hang off an answer belonging to this application.
        $file->loadMissing('answer');
        abort_unless($file->answer && (int) $file->answer->user_onboarding_id === (int) $userOnboarding->id, 404);

        $validated = $request->validate([
            'review_decision' => ['required', 'in:verified,rejected,resubmit_requested'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $file->update([
            'review_decision' => $validated['review_decision'],
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::guard('admin')->id(),
        ]);

        // Requesting a new upload tells the client to act — raise it as a
        // change request on that document's answer (in-app + email) so they
        // know a resubmission is required (EOP-99).
        if ($validated['review_decision'] === 'resubmit_requested') {
            $this->notifyDocumentResubmission($userOnboarding, $file, $validated['review_note'] ?? null);
        }

        return redirect()
            ->to(route('admin.user-onboardings.show', $userOnboarding).'#documents')
            ->with('success', $validated['review_decision'] === 'resubmit_requested'
                ? 'Resubmission requested — the client has been notified.'
                : 'Document review saved.');
    }

    /** Raise a change request + email so the client knows to re-upload a document. */
    private function notifyDocumentResubmission(UserOnboarding $userOnboarding, AnswerFile $file, ?string $note): void
    {
        $admin = Auth::guard('admin')->user();
        $file->loadMissing('answer.question');
        $answer = $file->answer;
        if (! $answer) {
            return;
        }

        $label = $answer->question->label ?? 'a document';
        $message = trim('Please re-upload the document for "'.$label.'".'.($note ? ' '.$note : ''));
        $notification = $this->notificationService->createChangeRequest($admin, $answer, $message);

        $user = $userOnboarding->user;
        try {
            if ($user?->email && $user->wantsEmail('change_requests')) {
                $subject = $this->emailService->getDefaultSubject('change_request', $label);
                $body = $this->emailService->getDefaultBody('change_request', [
                    'user_name' => $user->name ?? 'there',
                    'question_label' => $label,
                ]);
                $this->emailService->sendEmail($admin, $user, $subject, $body, $notification);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function toggleStep(UserOnboarding $userOnboarding, UserOnboardingStep $step): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        if ((int) $step->user_onboarding_id !== (int) $userOnboarding->id) {
            abort(404);
        }

        $wasCurrent = (int) $userOnboarding->current_step_id === (int) $step->id;

        if ($step->status === 'skipped') {
            $step->update(['status' => 'pending']);
            $message = "Step \"{$step->name}\" has been enabled.";

            // Adopt it when the client had nowhere to go.
            if (! $userOnboarding->current_step_id) {
                $step->update(['status' => 'in_progress', 'started_at' => now()]);
                $userOnboarding->update(['current_step_id' => $step->id, 'status' => 'in_progress']);
            }
        } else {
            $step->update(['status' => 'skipped', 'started_at' => null, 'completed_at' => null]);
            $message = "Step \"{$step->name}\" has been disabled (skipped).";

            // Skipping the step the client is on left current_step_id pointing
            // at a step the API filters out, so the portal showed "No active
            // onboarding step found" and the client was stuck (EOP-94).
            if ($wasCurrent) {
                $next = $userOnboarding->steps()
                    ->where('order', '>', $step->order)
                    ->whereNotIn('status', ['completed', 'skipped'])
                    ->orderBy('order')
                    ->first();

                if ($next) {
                    $next->update(['status' => 'in_progress', 'started_at' => now()]);
                    $userOnboarding->update(['current_step_id' => $next->id]);
                    $message .= " The client has been moved on to \"{$next->name}\".";
                } else {
                    // Nothing left to do — treat it as the client having
                    // finished rather than stranding them on a dead step.
                    $userOnboarding->update(['current_step_id' => null]);
                    $message .= ' It was the last outstanding step, so the application has no step in progress.';
                }
            }
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', $message);
    }

    /**
     * Acknowledge that an admin has looked at a client's response, clearing
     * it from the "awaiting check" queue.
     */
    public function checkResponse(Request $request, AdminNotification $notification): RedirectResponse
    {
        // Scope through the answer's application so an analyst can't clear
        // items belonging to a company they cannot see (EOP-92).
        $onboarding = $notification->userAnswer?->onboarding;
        if ($onboarding) {
            abort_unless($onboarding->isVisibleTo(Auth::guard('admin')->user()), 403);
        }

        if ($notification->status !== 'resolved') {
            return redirect()->back()->with('error', 'That request has not been answered yet.');
        }

        $notification->update([
            'checked_at' => now(),
            'checked_by' => Auth::guard('admin')->id(),
        ]);

        return redirect()->to($request->input('redirect_to', route('admin.dashboard')))
            ->with('success', 'Response marked as checked.');
    }

    public function archive(UserOnboarding $userOnboarding): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        // Only finished lifecycles are archivable — anything still moving
        // (draft, awaiting review) stays in the active lists.
        if (! in_array($userOnboarding->status, ['approved', 'rejected'], true)) {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', 'Only decided applications (approved or rejected) can be archived.');
        }

        $userOnboarding->update([
            'archived_at' => now(),
            'archived_by' => Auth::guard('admin')->id(),
        ]);

        return redirect()->route('admin.user-onboardings.index')
            ->with('success', 'Application archived — find it under the Archived filter.');
    }

    public function unarchive(UserOnboarding $userOnboarding): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        $userOnboarding->update(['archived_at' => null, 'archived_by' => null]);

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Application restored to the active list.');
    }

    public function assign(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        $validated = $request->validate([
            // Only an active reviewer (analyst or manager) may hold a company.
            'assigned_to' => ['nullable', \Illuminate\Validation\Rule::exists('admins', 'id')->where(
                fn ($q) => $q->where('is_active', true)->whereIn('role', [
                    \App\Enums\AdminRole::Analyst->value, \App\Enums\AdminRole::Manager->value,
                ]),
            )],
        ], [
            'assigned_to.exists' => 'You can only assign a company to an active analyst or manager.',
        ]);

        $newAssignee = $validated['assigned_to'] ? (int) $validated['assigned_to'] : null;
        $previousAssignee = $userOnboarding->assigned_to;
        $changed = $newAssignee !== $previousAssignee;

        $userOnboarding->update(['assigned_to' => $newAssignee]);

        $actingAdmin = Auth::guard('admin')->user();

        // Record the reassignment on the review timeline (who → whom).
        if ($changed) {
            $userOnboarding->reviewLogs()->create([
                'event' => $newAssignee ? 'assigned' : 'unassigned',
                'admin_id' => $actingAdmin->id,
                'comment' => $this->assignmentComment($previousAssignee, $newAssignee),
            ]);
        }

        // Notify the new assignee — unless they assigned it to themselves.
        if ($changed && $newAssignee !== null && $newAssignee !== $actingAdmin->id) {
            try {
                $assignee = \App\Models\Admin::find($newAssignee);
                if ($assignee?->email) {
                    \Illuminate\Support\Facades\Mail::to($assignee->email)
                        ->queue(new \App\Mail\OnboardingAssignedMail($userOnboarding->fresh(['user', 'userType']), $actingAdmin));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', $newAssignee ? 'Application assigned.' : 'Assignment cleared.');
    }

    /**
     * Assign (or clear the assignment of) several companies at once — a
     * manager distributing a batch of work. Only the caller's visible rows and
     * an active-reviewer target are honoured. No per-company email is sent for
     * a batch; the assignee sees the new work in their queue.
     */
    public function bulkAssign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:user_onboardings,id',
            'assigned_to' => ['nullable', \Illuminate\Validation\Rule::exists('admins', 'id')->where(
                fn ($q) => $q->where('is_active', true)->whereIn('role', [
                    \App\Enums\AdminRole::Analyst->value, \App\Enums\AdminRole::Manager->value,
                ]),
            )],
        ], [
            'assigned_to.exists' => 'You can only assign companies to an active analyst or manager.',
        ]);

        $assignee = $validated['assigned_to'] ? (int) $validated['assigned_to'] : null;
        $actor = Auth::guard('admin')->user();

        // Fetch first so we can record a per-application reassignment trail.
        $targets = UserOnboarding::visibleTo($actor)
            ->whereIn('id', $validated['ids'])
            ->get(['id', 'assigned_to']);

        UserOnboarding::whereIn('id', $targets->pluck('id'))->update(['assigned_to' => $assignee]);

        $now = now();
        $logs = $targets
            ->filter(fn ($o) => $o->assigned_to !== $assignee)
            ->map(fn ($o) => [
                'user_onboarding_id' => $o->id,
                'event' => $assignee ? 'assigned' : 'unassigned',
                'admin_id' => $actor->id,
                'comment' => $this->assignmentComment($o->assigned_to, $assignee),
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all();
        if ($logs) {
            \App\Models\OnboardingReviewLog::insert($logs);
        }

        $count = $targets->count();

        return redirect()->route('admin.user-onboardings.index', $request->except(['ids', 'assigned_to', '_token']))
            ->with(
                $count > 0 ? 'success' : 'error',
                $count > 0
                    ? "{$count} ".str('company')->plural($count).' '.($assignee ? 'assigned.' : 'unassigned.')
                    : 'No companies were updated.',
            );
    }

    /** Human-readable description of an assignment change for the timeline. */
    private function assignmentComment(?int $from, ?int $to): string
    {
        $name = fn (?int $id) => $id ? (Admin::find($id)?->name ?? 'a reviewer') : null;
        $fromName = $name($from);
        $toName = $name($to);

        if ($to === null) {
            return $fromName ? "Unassigned from {$fromName}" : 'Unassigned';
        }
        if ($from === null) {
            return "Assigned to {$toName}";
        }

        return "Reassigned from {$fromName} to {$toName}";
    }

    public function replyMessage(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        $validated = $request->validate(['body' => 'required|string|max:5000']);

        $message = $userOnboarding->messages()->create([
            'sender_type' => 'admin',
            'admin_id' => Auth::guard('admin')->id(),
            'body' => $validated['body'],
        ]);

        try {
            if ($userOnboarding->user?->email && $userOnboarding->user->wantsEmail('messages')) {
                \Illuminate\Support\Facades\Mail::to($userOnboarding->user->email)
                    ->queue(new \App\Mail\NewMessageMail($message));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Reply sent — the client sees it in their portal.');
    }

    public function exportPdf(UserOnboarding $userOnboarding, \App\Services\ApplicationPdfService $pdfService)
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        if (! in_array($userOnboarding->status, ['completed', 'approved', 'rejected'], true)) {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', 'The application PDF is available once the client has submitted.');
        }

        return $pdfService->render($userOnboarding)
            ->download("application-{$userOnboarding->reference}.pdf");
    }

    public function approve(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

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
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

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

    public function submitForApproval(UserOnboarding $userOnboarding): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        try {
            $this->onboardingService->submitForApproval($userOnboarding, Auth::guard('admin')->user());
        } catch (\DomainException $e) {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Submitted for approval — a second reviewer can now make the decision.');
    }

    public function escalate(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        $validated = $request->validate(['comment' => 'nullable|string|max:2000']);

        try {
            $this->onboardingService->escalate($userOnboarding, Auth::guard('admin')->user(), $validated['comment'] ?? null);
        } catch (\DomainException $e) {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Escalated to compliance for review.');
    }

    public function auditLogs(Request $request): View
    {
        // These are post-submission client changes only (AnswerService stops
        // logging draft edits) — the record of what moved after the team
        // started reviewing.
        $query = AnswerAuditLog::with(['question', 'user', 'editor', 'answer.onboarding.user']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('onboarding')) {
            $onboardingId = (int) $request->input('onboarding');
            $query->whereHas('answer', fn ($q) => $q->where('user_onboarding_id', $onboardingId));
        }

        $logs = $query->latest('edited_at')->paginate(20)->withQueryString();

        return view('admin.audit-logs.index', compact('logs'));
    }

    /**
     * Serve one document named by an audit-log row, so a reviewer can open both
     * the document a client replaced and the one they replaced it with. Client
     * Changes rendered each side as plain text, so neither was openable and the
     * reviewer had no way to compare them (retest items 40/41).
     *
     * The path is read out of the stored audit row, never taken from the
     * request — the index only chooses among that row's own uploads, so this
     * cannot be pointed at an arbitrary file.
     */
    public function auditLogDocument(Request $request, AnswerAuditLog $log, string $side, int $index): StreamedResponse
    {
        abort_unless(in_array($side, ['old', 'new'], true), 404);

        $onboarding = $log->answer?->onboarding;
        abort_unless($onboarding !== null, 404);
        abort_unless($onboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        $entries = AnswerValueFormatter::fileEntries($side === 'old' ? $log->old_value : $log->new_value);
        abort_unless(isset($entries[$index]), 404);

        $path = $entries[$index]['path'];
        abort_if($path === '', 404);

        // A replaced upload keeps its bytes but loses its answer_files row, so
        // fall back to the configured upload disk when the record is gone.
        $record = AnswerFile::where('s3_path', $path)->first();
        $disk = Storage::disk($record?->disk ?? config('onboarding_uploads.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response(
            $path,
            $entries[$index]['name'],
            ['Content-Type' => $record?->mime_type ?: 'application/octet-stream'],
            $request->boolean('download') ? 'attachment' : 'inline',
        );
    }

    public function requestChange(Request $request, UserOnboarding $userOnboarding, UserAnswer $answer): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        if ($userOnboarding->status === 'approved') {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', 'This application is already approved — changes cannot be requested on a decided application.');
        }

        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        if ((int) $answer->user_onboarding_id !== (int) $userOnboarding->id) {
            abort(404);
        }

        $admin = Auth::guard('admin')->user();
        $notification = $this->notificationService->createChangeRequest($admin, $answer, $request->input('message'));

        // If the answer's section was already signed off, requesting a change
        // reopens it — otherwise the completed marker (and the approval gate)
        // would survive a change the client still has to make (EOP-71).
        if ($answer->question?->question_group_id) {
            OnboardingSectionReview::where('user_onboarding_id', $userOnboarding->id)
                ->where('question_group_id', $answer->question->question_group_id)
                ->where('status', 'completed')
                ->update(['status' => 'in_progress', 'reviewed_at' => null]);
        }

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
            if ($user->wantsEmail('change_requests')) {
                $this->emailService->sendEmail($admin, $user, $subject, $body, $notification);
            }
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', 'Change request created, but the email notification could not be sent.');
        }

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Change request sent to user.');
    }

    public function createQuestion(UserOnboarding $userOnboarding): View|RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        if ($userOnboarding->status === 'approved') {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', 'This application is already approved — new questions cannot be added to a decided application.');
        }

        $userOnboarding->load(['user']);

        // So the admin can say which section the follow-up relates to (EOP-95).
        $questionGroups = QuestionGroup::where('is_active', true)->orderBy('order')->get();

        return view('admin.user-onboardings.new-question', compact('userOnboarding', 'questionGroups'));
    }

    public function storeQuestion(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        abort_unless($userOnboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        if ($userOnboarding->status === 'approved') {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', 'This application is already approved — new questions cannot be added to a decided application.');
        }

        $validated = $request->validate([
            'label' => 'required|string|max:500',
            'description' => 'nullable|string|max:2000',
            // `table` is offered in the form but was missing here, so choosing
            // it always failed validation (found while triaging EOP-95).
            'type' => 'required|in:text,radio,date,select,multi_select,textarea,number,file,table',
            // Which section the follow-up relates to (EOP-95).
            'question_group_id' => 'nullable|exists:question_groups,id',
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
            'question_group_id' => $validated['question_group_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'options' => isset($validated['options']) ? json_decode($validated['options'], true) : null,
            'is_required' => $request->boolean('is_required'),
            'placeholder' => $validated['placeholder'] ?? null,
            'help_text' => $validated['help_text'] ?? null,
        ];

        $notification = $this->notificationService->createNewQuestion($admin, $user, $questionData, $validated['message']);

        // Send email if requested (and the client hasn't muted the category)
        if ($request->boolean('send_email') && $user->wantsEmail('change_requests')) {
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
