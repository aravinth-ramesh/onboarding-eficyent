<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Concerns\ParsesDateRange;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AdminQuestion;
use App\Models\AnswerAuditLog;
use App\Models\FilterPreset;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use App\Models\UserType;
use App\Services\AdminEmailService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
        $admins = \App\Models\Admin::where('is_active', true)->orderBy('name')->get();

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
            'activeFilterSummary' => $this->describeFilters($active, $userTypes, $admins),
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
        $filename = 'onboardings-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Reference', 'Name', 'Email', 'Organization Type', 'Subcategory',
                'Status', 'Resubmission', 'Country', 'Assigned To', 'Started', 'Submitted',
                'Decided', 'Decided By', 'Decision Comment',
            ]);

            $this->filteredQuery($request)
                ->with(['decidedBy', 'assignee'])
                ->latest()
                ->lazy()
                ->each(function (UserOnboarding $o) use ($out) {
                    fputcsv($out, [
                        $o->reference,
                        $o->user->name ?? '',
                        $o->user->email ?? '',
                        $o->userType->name ?? '',
                        $o->subcategory->name ?? '',
                        $o->status,
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
                "Email scheduled for {$scheduled->send_at->format('M d, Y H:i')} to " . count($validated['ids']) . ' client(s).');
        }

        $sent = $this->emailService->sendBulk($admin, $validated['ids'], $validated['subject'], $validated['body']);
        $skipped = count($validated['ids']) - $sent;

        $message = "Email queued to {$sent} client(s)."
            . ($skipped > 0 ? " {$skipped} skipped (no email address or send failed)." : '');

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

        foreach (UserOnboarding::whereIn('id', $validated['ids'])->get() as $onboarding) {
            try {
                $validated['decision'] === 'approve'
                    ? $this->onboardingService->approve($onboarding, $admin, $validated['comment'] ?? null)
                    : $this->onboardingService->reject($onboarding, $admin, $validated['comment']);
                $decided++;
            } catch (\DomainException) {
                $skipped++;
            }
        }

        $verb = $validated['decision'] === 'approve' ? 'approved' : 'rejected';
        $message = "{$decided} application(s) {$verb}."
            . ($skipped > 0 ? " {$skipped} skipped (not awaiting review)." : '');

        return redirect()->route('admin.user-onboardings.index', $request->query())
            ->with($decided > 0 ? 'success' : 'error', $message);
    }

    private function filteredQuery(Request $request)
    {
        $query = UserOnboarding::with(['user', 'userType', 'subcategory']);

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
                $q->whereHas('user', function ($u) use ($term) {
                    $u->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
                if ($referenceId !== null) {
                    $q->orWhere('id', $referenceId);
                }
            });
        }

        return $query;
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
            'notes.admin',
            'messages.admin',
            'assignee',
        ]);

        // Viewing the thread counts as reading the client's messages.
        $userOnboarding->messages()
            ->where('sender_type', 'client')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

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

        $admins = \App\Models\Admin::where('is_active', true)->orderBy('name')->get();

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

    /**
     * Acknowledge that an admin has looked at a client's response, clearing
     * it from the "awaiting check" queue.
     */
    public function checkResponse(Request $request, AdminNotification $notification): RedirectResponse
    {
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
        $userOnboarding->update(['archived_at' => null, 'archived_by' => null]);

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Application restored to the active list.');
    }

    public function assign(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:admins,id',
        ]);

        $newAssignee = $validated['assigned_to'] ? (int) $validated['assigned_to'] : null;
        $changed = $newAssignee !== $userOnboarding->assigned_to;

        $userOnboarding->update(['assigned_to' => $newAssignee]);

        $actingAdmin = Auth::guard('admin')->user();

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

    public function replyMessage(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
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
        if (! in_array($userOnboarding->status, ['completed', 'approved', 'rejected'], true)) {
            return redirect()->route('admin.user-onboardings.show', $userOnboarding)
                ->with('error', 'The application PDF is available once the client has submitted.');
        }

        return $pdfService->render($userOnboarding)
            ->download("application-{$userOnboarding->reference}.pdf");
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
