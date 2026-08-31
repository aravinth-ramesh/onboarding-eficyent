@extends('admin.layouts.app')

@section('title', 'Onboarding Details')

@push('styles')
<style>
    .submitted-answers-section-label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--color-accent, #2e86de);
        margin-bottom: 8px;
        padding-bottom: 6px;
        border-bottom: 2px solid var(--color-accent, #2e86de);
        display: inline-block;
    }
    /* Clear per-section review state: a tinted card with a coloured left bar
       so reviewed / in-review / pending sections are distinguishable at a
       glance, not just by a small badge (EOP-70). */
    .review-section {
        border: 1px solid #e9edf2;
        border-left: 4px solid #cbd3dc;
        border-radius: 8px;
        padding: 14px 16px;
        transition: background-color .15s ease, border-color .15s ease;
    }
    .review-section.status-in_progress {
        border-left-color: #3b82f6;
        background: rgba(59, 130, 246, 0.04);
    }
    .review-section.status-completed {
        border-left-color: #10b981;
        background: rgba(16, 185, 129, 0.055);
    }
    .submitted-answers-table {
        width: 100%;
        border-collapse: collapse;
    }
    .submitted-answers-table tr {
        border-bottom: 1px solid #f0f2f5;
    }
    .submitted-answers-table tr:last-child {
        border-bottom: none;
    }
    .submitted-answers-table td {
        padding: 10px 12px;
        vertical-align: top;
        font-size: 0.875rem;
        /* A long unbroken value (name, ID, URL) must wrap inside its cell
           rather than stretch the review layout (EOP-69). */
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .submitted-answers-label {
        width: 35%;
        color: #6c757d;
        font-weight: 500;
        overflow-wrap: anywhere;
    }
    .submitted-answers-value {
        color: #2c3e50;
        font-weight: 500;
        max-width: 0;
        overflow-wrap: anywhere;
    }
    /* Nested tables (UBO / directors rows) inherit the same wrapping. */
    .submitted-answers-value table {
        table-layout: fixed;
        width: 100%;
    }
    .submitted-answers-value table td,
    .submitted-answers-value table th {
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .submitted-answers-file-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--color-accent, #2e86de);
        text-decoration: none;
        font-size: 0.85rem;
        padding: 3px 0;
    }
    .submitted-answers-file-link:hover {
        text-decoration: underline;
        color: var(--color-primary-dark, #0f2440);
    }
    .submitted-answers-file-link i {
        font-size: 0.9rem;
    }
    .submitted-answers-actions {
        width: 100px;
        text-align: right;
        vertical-align: middle !important;
        white-space: nowrap;
    }
    .submitted-answers-history-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        color: #6c757d;
        transition: all 0.15s;
        text-decoration: none;
        position: relative;
    }
    .submitted-answers-history-link:hover {
        background: #e9ecef;
        color: var(--color-accent, #2e86de);
    }
    .submitted-answers-history-link .history-badge {
        position: absolute;
        top: -2px;
        right: -2px;
        font-size: 0.6rem;
        min-width: 16px;
        height: 16px;
        line-height: 16px;
        border-radius: 8px;
        background: var(--color-accent, #2e86de);
        color: #fff;
        text-align: center;
        padding: 0 4px;
    }
    .btn-request-change {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        color: #6c757d;
        transition: all 0.15s;
        text-decoration: none;
        border: none;
        background: none;
        cursor: pointer;
        padding: 0;
    }
    .btn-request-change:hover {
        background: #fff3cd;
        color: var(--color-warning, #f39c12);
    }
    .btn-request-change.has-pending {
        color: var(--color-warning, #f39c12);
    }
    .notification-status-badge {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 600;
    }
    .notification-status-badge.pending {
        background: #fff3cd;
        color: #856404;
    }
    .notification-status-badge.resolved {
        background: #d4edda;
        color: #155724;
    }

    /* Onboarding detail tables (UBO, ownership, signatories, bank accounts)
       were squeezed until headings and values wrapped one or two characters
       per line. Give the table a floor width and let it scroll inside its
       own container rather than compressing the columns (retest item 32). */
    /* `.submitted-answers-value table` above sets table-layout: fixed, which
       ignores min-width and splits the width equally between columns. With
       eleven columns that left ~58px each, and because the headings below are
       nowrap they overflowed their cell and painted over the neighbouring
       heading rather than wrapping or scrolling (report item 6). Opting this
       table back into auto layout is what makes the widths below take effect. */
    .submitted-answers-value table.answer-table {
        table-layout: auto;
        width: auto;
        min-width: 100%;
    }
    .answer-table { min-width: 680px; }
    .answer-table th { white-space: nowrap; }
    .answer-table td { min-width: 110px; vertical-align: top; word-break: normal; overflow-wrap: anywhere; }
</style>
@endpush

@section('actions')
    <div class="d-flex gap-2">
        @if($userOnboarding->archived_at)
            <form method="POST" action="{{ route('admin.user-onboardings.unarchive', $userOnboarding) }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-up-square"></i> Unarchive
                </button>
            </form>
        @elseif(in_array($userOnboarding->status, ['approved', 'rejected']))
            <form method="POST" action="{{ route('admin.user-onboardings.archive', $userOnboarding) }}"
                  onsubmit="return confirm('Archive this application? It will leave the active list but stays fully accessible.')">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-archive"></i> Archive
                </button>
            </form>
        @endif
        @if(in_array($userOnboarding->status, ['completed', 'approved', 'rejected']))
            <a href="{{ route('admin.user-onboardings.export-pdf', $userOnboarding) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-file-earmark-pdf"></i> Export PDF
            </a>
        @endif
        <a href="{{ route('admin.user-onboardings.new-question', $userOnboarding) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-circle"></i> New Question
        </a>
        <a href="{{ route('admin.user-onboardings.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="row g-3 mb-4">
    {{-- User Info --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Application Details</div>
            <div class="card-body">
                <dl class="mb-0">
                    @if($userOnboarding->company_name)
                        <dt class="text-muted" style="font-size: 0.8rem;">Company</dt>
                        <dd class="fw-semibold">{{ $userOnboarding->company_name }}</dd>
                    @endif

                    <dt class="text-muted" style="font-size: 0.8rem;">Contact</dt>
                    <dd>{{ $userOnboarding->user->name ?? 'N/A' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Email</dt>
                    <dd>{{ $userOnboarding->user->email ?? 'N/A' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">User Type</dt>
                    <dd>{{ $userOnboarding->userType->name ?? 'N/A' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Subcategory</dt>
                    <dd>{{ $userOnboarding->subcategory->name ?? '-' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Status</dt>
                    <dd>
                        <span class="badge badge-{{ $userOnboarding->status }}">
                            {{ $userOnboarding->statusLabel }}
                        </span>
                        @if($userOnboarding->reopened_at)
                            <span class="badge bg-info-subtle text-info-emphasis border" title="Reopened after rejection on {{ $userOnboarding->reopened_at->format('M d, Y H:i') }}">
                                Resubmission
                            </span>
                        @endif
                        @if($userOnboarding->archived_at)
                            <span class="badge bg-secondary-subtle text-secondary border" title="Archived by {{ $userOnboarding->archivedBy->name ?? 'admin' }} on {{ $userOnboarding->archived_at->format('M d, Y H:i') }}">
                                Archived
                            </span>
                        @endif
                        @include('admin.user-onboardings._aging-badge', ['aging' => $userOnboarding->reviewAging()])
                    </dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Assigned To</dt>
                    <dd>
                        <form method="POST" action="{{ route('admin.user-onboardings.assign', $userOnboarding) }}">
                            @csrf
                            <select name="assigned_to" class="form-select form-select-sm" onchange="this.form.submit()"
                                    style="max-width: 220px;">
                                <option value="">— Unassigned —</option>
                                @foreach($admins as $adminOption)
                                    <option value="{{ $adminOption->id }}" @selected($userOnboarding->assigned_to === $adminOption->id)>
                                        {{ $adminOption->name }}{{ $adminOption->id === auth('admin')->id() ? ' (me)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Template Version</dt>
                    <dd>{{ $userOnboarding->template_version ?? '-' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Started</dt>
                    <dd>{{ $userOnboarding->started_at?->format('M d, Y H:i') ?? '-' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Completed</dt>
                    <dd class="mb-0">{{ $userOnboarding->completed_at?->format('M d, Y H:i') ?? '-' }}</dd>
                </dl>

                @if($userOnboarding->status === 'completed')
                    @php
                        $meDecide = auth('admin')->user();
                        $canSubmit = $meDecide?->hasAbility(\App\Enums\Ability::SUBMIT_FOR_APPROVAL);
                        $canApprove = $meDecide?->hasAbility(\App\Enums\Ability::APPROVE_ONBOARDING);
                        $canReject = $meDecide?->hasAbility(\App\Enums\Ability::REJECT_ONBOARDING);
                        $canEscalate = $meDecide?->hasAbility(\App\Enums\Ability::ESCALATE_ONBOARDING);
                        $isMaker = $userOnboarding->submitted_for_approval_by
                            && (int) $userOnboarding->submitted_for_approval_by === (int) $meDecide?->id;
                        $decideProgress = $userOnboarding->sectionReviewProgress();
                        $sectionsDone = $decideProgress['total'] === 0 || $decideProgress['complete'];
                        // Assigned work belongs to its reviewer — approve and
                        // reject are gated identically (EOP-89).
                        $mayDecide = $meDecide
                            && app(\App\Services\OnboardingService::class)->canDecide($userOnboarding, $meDecide);
                        $notMineNote = 'Assigned to ' . ($userOnboarding->assignee->name ?? 'another reviewer')
                            . ' — they decide, or it must be submitted for approval or escalated first';
                    @endphp
                    <hr>

                    {{-- Where the application sits in the four-eyes hand-off. --}}
                    @if($userOnboarding->isEscalated())
                        <div class="p-2 rounded bg-warning-subtle border mb-2" style="font-size: 0.85rem;">
                            <i class="bi bi-flag-fill text-warning"></i> <strong>Escalated to compliance</strong>
                            @if($userOnboarding->submittedForApprovalBy)
                                <div class="text-muted">Submitted by {{ $userOnboarding->submittedForApprovalBy->name }}</div>
                            @endif
                        </div>
                    @elseif($userOnboarding->approval_state === 'pending_approval')
                        <div class="p-2 rounded bg-info-subtle border mb-2" style="font-size: 0.85rem;">
                            <i class="bi bi-hourglass-split text-info"></i> <strong>Awaiting a second reviewer</strong>
                            <div class="text-muted">
                                Submitted for approval by {{ $userOnboarding->submittedForApprovalBy->name ?? 'a reviewer' }}
                                {{ $userOnboarding->submitted_for_approval_at?->diffForHumans() }}
                            </div>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-2">
                        {{-- Maker step: hand off for approval once every section is reviewed. --}}
                        @if($canSubmit && $userOnboarding->approval_state !== 'pending_approval')
                            <form method="POST" action="{{ route('admin.user-onboardings.submit-for-approval', $userOnboarding) }}">
                                @csrf
                                <button class="btn btn-primary btn-sm" @disabled(!$sectionsDone)
                                        title="{{ $sectionsDone ? 'Hand off for a second reviewer to approve' : 'Review every section first' }}">
                                    <i class="bi bi-send-check"></i> Submit for approval
                                </button>
                            </form>
                        @endif

                        {{-- Checker step: a second reviewer approves or rejects. --}}
                        @if($canApprove)
                            <form method="POST" action="{{ route('admin.user-onboardings.approve', $userOnboarding) }}"
                                  onsubmit="return confirm('Approve this application? The client will be notified by email.')">
                                @csrf
                                <button class="btn btn-success btn-sm" @disabled($isMaker || !$sectionsDone || !$mayDecide)
                                        title="{{ $isMaker ? 'You submitted this — a different reviewer must approve (four-eyes)' : (!$mayDecide ? $notMineNote : (!$sectionsDone ? 'Every section must be reviewed first' : 'Approve')) }}">
                                    <i class="bi bi-check-circle"></i> Approve
                                </button>
                            </form>
                        @endif
                        @if($canReject)
                            <button type="button" class="btn btn-outline-danger btn-sm" @disabled($isMaker || !$mayDecide)
                                    title="{{ $isMaker ? 'You submitted this — a different reviewer must decide (four-eyes)' : (!$mayDecide ? $notMineNote : 'Reject') }}"
                                    data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-x-circle"></i> Reject
                            </button>
                        @endif

                        {{-- Refer to compliance. --}}
                        @if($canEscalate && !$userOnboarding->isEscalated())
                            <button type="button" class="btn btn-outline-warning btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#escalateModal">
                                <i class="bi bi-flag"></i> Escalate
                            </button>
                        @endif
                    </div>
                    {{-- A title="" on a disabled button never surfaces — browsers
                         suppress its events — so the reason Approve was greyed
                         out was invisible (EOP-91). State it in the page. --}}
                    @if($isMaker)
                        <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> You submitted this for approval, so a second reviewer must make the decision.</div>
                    @elseif(!$mayDecide)
                        <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ $notMineNote }}.</div>
                    @elseif(!$sectionsDone)
                        <div class="small text-muted mt-2">
                            <i class="bi bi-info-circle"></i>
                            Approval needs every section reviewed — {{ $decideProgress['done'] }} of {{ $decideProgress['total'] }} done so far. Rejection is available at any point.
                        </div>
                    @endif
                @elseif(in_array($userOnboarding->status, ['approved', 'rejected']))
                    <hr>
                    <div class="p-2 rounded {{ $userOnboarding->status === 'approved' ? 'bg-success-subtle' : 'bg-danger-subtle' }}" style="font-size: 0.85rem;">
                        <strong>{{ ucfirst($userOnboarding->status) }}</strong>
                        by {{ $userOnboarding->decidedBy->name ?? 'admin' }}
                        on {{ $userOnboarding->decided_at?->format('M d, Y H:i') }}
                        @if($userOnboarding->decision_comment)
                            <div class="mt-1 fst-italic">"{{ $userOnboarding->decision_comment }}"</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Internal notes — admin-only; never shown to the client. --}}
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Internal Notes</span>
                <span class="badge bg-secondary-subtle text-secondary border" title="Notes are only visible in the admin panel">
                    <i class="bi bi-eye-slash"></i> Not visible to client
                </span>
            </div>
            <div class="card-body py-2">
                @forelse($userOnboarding->notes as $note)
                    <div class="d-flex gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}" style="font-size: 0.85rem;">
                        <div class="flex-grow-1">
                            <strong>{{ $note->admin->name ?? 'Admin' }}</strong>
                            <span class="text-muted">· {{ $note->created_at->format('M d, Y H:i') }}</span>
                            <div style="white-space: pre-wrap;">{{ $note->note }}</div>
                        </div>
                        @if($note->admin_id === auth('admin')->id())
                            <form method="POST" action="{{ route('admin.user-onboardings.notes.destroy', [$userOnboarding, $note]) }}"
                                  onsubmit="return confirm('Delete this note?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger p-0" title="Delete note">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="text-muted py-2" style="font-size: 0.85rem;">No notes yet.</div>
                @endforelse

                <form method="POST" action="{{ route('admin.user-onboardings.notes.store', $userOnboarding) }}" class="mt-2">
                    @csrf
                    <textarea name="note" class="form-control form-control-sm mb-2" rows="2" required
                              placeholder="Add an internal note for the team..."></textarea>
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-journal-plus"></i> Add Note
                    </button>
                </form>
            </div>
        </div>

        @if($userOnboarding->reviewLogs->isNotEmpty())
            {{-- Full review timeline — survives reopening, so past rejection
                 reasons stay visible across resubmission rounds. --}}
            <div class="card mt-3">
                <div class="card-header">Review History</div>
                <div class="card-body py-2">
                    @foreach($userOnboarding->reviewLogs as $log)
                        @php
                            $meta = [
                                'submitted' => ['icon' => 'bi-send', 'class' => 'text-primary', 'label' => 'Submitted'],
                                'resubmitted' => ['icon' => 'bi-arrow-repeat', 'class' => 'text-primary', 'label' => 'Resubmitted'],
                                'approved' => ['icon' => 'bi-check-circle-fill', 'class' => 'text-success', 'label' => 'Approved'],
                                'rejected' => ['icon' => 'bi-x-circle-fill', 'class' => 'text-danger', 'label' => 'Rejected'],
                                'reopened' => ['icon' => 'bi-unlock', 'class' => 'text-secondary', 'label' => 'Reopened by client'],
                                'submitted_for_approval' => ['icon' => 'bi-send-check', 'class' => 'text-primary', 'label' => 'Submitted for approval'],
                                'escalated' => ['icon' => 'bi-flag-fill', 'class' => 'text-warning', 'label' => 'Escalated to compliance'],
                                'assigned' => ['icon' => 'bi-person-check', 'class' => 'text-secondary', 'label' => 'Assigned'],
                                'unassigned' => ['icon' => 'bi-person-dash', 'class' => 'text-secondary', 'label' => 'Unassigned'],
                            ][$log->event] ?? ['icon' => 'bi-dot', 'class' => 'text-muted', 'label' => ucfirst(str_replace('_', ' ', $log->event))];
                        @endphp
                        <div class="d-flex gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}" style="font-size: 0.85rem;">
                            <i class="bi {{ $meta['icon'] }} {{ $meta['class'] }}"></i>
                            <div class="flex-grow-1">
                                <strong>{{ $meta['label'] }}</strong>
                                @if($log->admin)
                                    by {{ $log->admin->name }}
                                @endif
                                <span class="text-muted">· {{ $log->created_at->format('M d, Y H:i') }}</span>
                                @if($log->comment)
                                    <div class="fst-italic text-muted mt-1">"{{ $log->comment }}"</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Steps --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Onboarding Steps</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Step</th>
                                <th>Component</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Completed</th>
                                <th style="width: 80px;">Toggle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($userOnboarding->steps->sortBy('order') as $step)
                                <tr class="{{ $step->status === 'skipped' ? 'table-warning opacity-75' : ($step->id === $userOnboarding->current_step_id ? 'table-primary' : '') }}">
                                    <td>{{ $step->order }}</td>
                                    <td class="fw-semibold">
                                        {{ $step->name }}
                                        @if($step->id === $userOnboarding->current_step_id)
                                            <span class="badge bg-primary ms-1">Current</span>
                                        @endif
                                    </td>
                                    <td><code>{{ $step->component_key }}</code></td>
                                    <td>
                                        <span class="badge badge-{{ $step->status }}">
                                            {{ ucfirst(str_replace('_', ' ', $step->status)) }}
                                        </span>
                                    </td>
                                    <td>{{ $step->started_at?->format('M d, H:i') ?? '-' }}</td>
                                    <td>{{ $step->completed_at?->format('M d, H:i') ?? '-' }}</td>
                                    <td>
                                        @if($step->status !== 'completed')
                                            <form action="{{ route('admin.user-onboardings.steps.toggle', [$userOnboarding, $step]) }}" method="POST"
                                                onsubmit="return confirm('{{ $step->status === 'skipped' ? 'Enable this step?' : 'Disable (skip) this step?' }}')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $step->status === 'skipped' ? 'btn-outline-success' : 'btn-outline-warning' }} btn-action"
                                                    title="{{ $step->status === 'skipped' ? 'Enable step' : 'Disable step' }}">
                                                    <i class="bi {{ $step->status === 'skipped' ? 'bi-toggle-off' : 'bi-toggle-on' }}"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted" title="Completed steps cannot be toggled"><i class="bi bi-lock"></i></span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">No steps found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Client messages thread --}}
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-chat-dots"></i> Messages with Client
    </div>
    <div class="card-body py-2">
        @forelse($userOnboarding->messages as $message)
            <div class="d-flex py-2 {{ $loop->last ? '' : 'border-bottom' }}" style="font-size: 0.88rem;">
                <div class="flex-grow-1 {{ $message->sender_type === 'admin' ? 'text-end' : '' }}">
                    <div>
                        <strong>{{ $message->sender_type === 'admin' ? ($message->admin->name ?? 'Team') : ($userOnboarding->user->name ?? 'Client') }}</strong>
                        <span class="text-muted">· {{ $message->created_at->format('M d, Y H:i') }}</span>
                    </div>
                    <div class="d-inline-block px-3 py-2 mt-1 rounded {{ $message->sender_type === 'admin' ? 'bg-primary-subtle' : 'bg-light border' }}"
                         style="white-space: pre-wrap; max-width: 80%; text-align: left;">{{ $message->body }}</div>
                </div>
            </div>
        @empty
            <div class="text-muted py-2" style="font-size: 0.85rem;">No messages yet.</div>
        @endforelse

        <form method="POST" action="{{ route('admin.user-onboardings.messages.reply', $userOnboarding) }}" class="mt-2">
            @csrf
            <div class="d-flex gap-2">
                <textarea name="body" class="form-control form-control-sm" rows="2" required
                          placeholder="Reply to the client... (they are notified by email)"></textarea>
                <button class="btn btn-sm btn-primary align-self-end">
                    <i class="bi bi-send"></i> Send
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Answers --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Submitted Answers</span>
        <span class="badge badge-{{ $userOnboarding->status }}">
            {{ $userOnboarding->statusLabel }}
        </span>
    </div>
    <div class="card-body">
        @php
            $grouped = $userOnboarding->answers
                ->filter(fn($a) => $a->question && $a->question->group)
                ->sortBy([
                    fn($a) => $a->question->group->order ?? 0,
                    fn($a) => $a->question->order ?? 0,
                ])
                ->groupBy(fn($a) => $a->question->group->id);

            $me = auth('admin')->user();
            $canReview = $me?->hasAbility(\App\Enums\Ability::REVIEW_ONBOARDING);
            $reviewByGroup = $userOnboarding->sectionReviews->keyBy('question_group_id');
            $sectionProgress = $userOnboarding->sectionReviewProgress();
            // Answers the client edited after submitting (audit logs only exist
            // for post-submission changes now).
            $changedAfterSubmission = $userOnboarding->answers->filter(fn($a) => ($a->audit_logs_count ?? 0) > 0)->count();
            $sectionBadges = [
                'pending' => ['Not started', 'bg-light text-muted border'],
                'in_progress' => ['In review', 'bg-info-subtle text-info-emphasis border'],
                'completed' => ['Reviewed', 'bg-success-subtle text-success border'],
            ];
        @endphp

        {{-- Flag when the client changed answers after submitting — the team
             needs to re-check those. Each changed answer keeps its history link. --}}
        @if($changedAfterSubmission > 0)
            <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-4" style="font-size: 0.9rem;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>
                    <strong>{{ $changedAfterSubmission }}</strong>
                    {{ \Illuminate\Support\Str::plural('answer', $changedAfterSubmission) }} changed by the client after submission —
                    look for the <i class="bi bi-clock-history"></i> history icon on the changed {{ \Illuminate\Support\Str::plural('answer', $changedAfterSubmission) }} below.
                </span>
            </div>
        @endif

        {{-- Reviewer progress across the application's sections. Persists so a
             long review can be picked up again on another day. --}}
        @if($sectionProgress['total'] > 0)
            <div class="section-review-progress mb-4" id="section-review-progress"
                 data-total="{{ $sectionProgress['total'] }}"
                 data-complete="{{ $sectionProgress['complete'] ? '1' : '0' }}">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold">Review progress</span>
                    <span class="text-muted small js-progress-text">{{ $sectionProgress['done'] }} of {{ $sectionProgress['total'] }} sections reviewed</span>
                </div>
                <div class="progress" style="height: 8px;" role="progressbar" aria-valuenow="{{ $sectionProgress['done'] }}" aria-valuemin="0" aria-valuemax="{{ $sectionProgress['total'] }}">
                    <div class="progress-bar js-progress-bar {{ $sectionProgress['complete'] ? 'bg-success' : '' }}" style="width: {{ $sectionProgress['total'] ? round($sectionProgress['done'] / $sectionProgress['total'] * 100) : 0 }}%;"></div>
                </div>
                <div class="small text-success mt-1 js-progress-complete" @style(['display:none' => !$sectionProgress['complete']])><i class="bi bi-check2-circle"></i> All sections reviewed — ready for a decision.</div>
            </div>
        @endif

        @forelse($grouped as $groupId => $groupAnswers)
            @php
                $groupName = $groupAnswers->first()->question->group->name;
                $review = $reviewByGroup->get($groupId);
                $reviewStatus = $review->status ?? 'pending';
                [$badgeLabel, $badgeClass] = $sectionBadges[$reviewStatus];
            @endphp
            <div class="review-section status-{{ $reviewStatus }} {{ !$loop->first ? 'mt-4' : '' }}" id="section-{{ $groupId }}">
                <div class="submitted-answers-section-label d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span>
                        <i class="bi bi-check-circle-fill text-success me-1 js-section-check" @style(['display:none' => $reviewStatus !== 'completed'])></i>
                        {{ $groupName }}
                        <span class="badge {{ $badgeClass }} ms-1 align-middle js-section-badge">{{ $badgeLabel }}</span>
                    </span>
                    @if($canReview)
                        <form method="POST" action="{{ route('admin.user-onboardings.sections.review', [$userOnboarding, $groupId]) }}" class="section-review-form d-flex align-items-center gap-1">
                            @csrf
                            <input type="text" name="note" class="form-control form-control-sm section-review-note" placeholder="Note (optional)" value="{{ $review->note ?? '' }}" style="max-width: 200px;">
                            {{-- Sign-off is one-way: a reviewed section reopens only
                                 through a change request or an application reopen (EOP-74). --}}
                            <select name="status" class="form-select form-select-sm" style="width: auto;">
                                <option value="pending" @selected($reviewStatus === 'pending') @disabled($reviewStatus === 'completed')>Not started</option>
                                <option value="in_progress" @selected($reviewStatus === 'in_progress') @disabled($reviewStatus === 'completed')>In review</option>
                                <option value="completed" @selected($reviewStatus === 'completed')>Reviewed</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                        </form>
                    @endif
                </div>
                <div class="small text-muted mb-1 js-section-reviewed-meta" @style(['display:none' => !($review && $review->reviewed_at)])>
                    <i class="bi bi-check2"></i> <span class="js-section-reviewed-text">@if($review && $review->reviewed_at)Reviewed {{ $review->reviewed_at->diffForHumans() }}{{ $review->reviewer ? ' by ' . $review->reviewer->name : '' }}@endif</span>
                </div>
                <table class="submitted-answers-table">
                    <tbody>
                        @foreach($groupAnswers as $answer)
                            @php
                                $question = $answer->question;
                                $type = $question->type ?? 'text';
                                $options = $question->options ?? [];
                                $val = $answer->value;
                                $hasPendingRequest = in_array($answer->id, $pendingChangeRequestAnswerIds);
                            @endphp
                            <tr>
                                <td class="submitted-answers-label">
                                    {{ $question->label ?? 'N/A' }}
                                    @if($hasPendingRequest)
                                        <span class="notification-status-badge pending ms-1">Change Requested</span>
                                    @endif
                                </td>
                                <td class="submitted-answers-value">
                                    @if($type === 'file' && $answer->files->count())
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($answer->files as $file)
                                                <div>
                                                    <a href="{{ route('admin.documents.show', $file) }}" target="_blank" class="submitted-answers-file-link" title="Preview in a new tab">
                                                        <i class="bi bi-paperclip"></i>
                                                        {{ $file->original_filename }}
                                                        <small class="text-muted ms-1">({{ $file->file_size < 1048576 ? number_format($file->file_size / 1024, 1) . ' KB' : number_format($file->file_size / 1048576, 1) . ' MB' }})</small>
                                                    </a>
                                                    <a href="{{ route('admin.documents.show', [$file, 'download' => 1]) }}" class="text-muted ms-1 small text-decoration-none" title="Download">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    @switch($file->validation_status)
                                                        @case('passed')
                                                            <span class="badge bg-success-subtle text-success border ms-1" title="{{ $file->validation_summary }}">AI verified{{ $file->detected_type ? ': ' . config("document_validation.types.{$file->detected_type}.label", $file->detected_type) : '' }}</span>
                                                            @break
                                                        @case('needs_review')
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border ms-1" title="{{ $file->validation_summary }}">Needs review</span>
                                                            @break
                                                        @case('type_mismatch')
                                                        @case('expired')
                                                        @case('stale')
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border ms-1" title="{{ $file->validation_summary }}">
                                                                {{ ['type_mismatch' => 'Wrong document type', 'expired' => 'Expired', 'stale' => 'Outdated'][$file->validation_status] }}
                                                                — justified
                                                            </span>
                                                            @break
                                                    @endswitch
                                                    @if($file->justification)
                                                        <div class="small text-muted fst-italic mt-1">
                                                            <i class="bi bi-chat-quote"></i> Client justification: {{ $file->justification }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif($type === 'file')
                                        <span class="text-muted">&mdash;</span>
                                    @elseif($type === 'multi_select')
                                        @php
                                            $selected = is_string($val) ? json_decode($val, true) : ($val ?? []);
                                            $labels = collect($selected)->map(function ($v) use ($options) {
                                                $opt = collect($options)->firstWhere('value', $v);
                                                return $opt['label'] ?? $v;
                                            });
                                        @endphp
                                        @if(count($labels))
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($labels as $label)
                                                    <span class="badge bg-light text-dark border">{{ $label }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    {{-- `mcc` included: it stores an industry code, which showed
                                         as a bare "5942" instead of the industry name. --}}
                                    @elseif(in_array($type, ['radio', 'select', 'mcc']))
                                        @php
                                            $opt = collect($options)->firstWhere('value', $val);
                                        @endphp
                                        {{ $opt['label'] ?? $val ?? '—' }}
                                    @elseif($type === 'table')
                                        @php
                                            $tableRows = is_string($val) ? json_decode($val, true) : ($val ?? []);
                                            $columns = $options['columns'] ?? [];
                                        @endphp
                                        @if(!empty($tableRows) && !empty($columns))
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0 answer-table" style="font-size: 0.82rem;">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width: 40px;">#</th>
                                                            @foreach($columns as $col)
                                                                <th>{{ $col['label'] }}</th>
                                                            @endforeach
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($tableRows as $rowIdx => $row)
                                                            <tr>
                                                                <td class="text-muted">{{ $rowIdx + 1 }}</td>
                                                                @foreach($columns as $col)
                                                                    <td>
                                                                        @php $cellVal = $row[$col['key']] ?? ''; @endphp
                                                                        @if(($col['type'] ?? null) === 'file')
                                                                            @if(is_array($cellVal) && (!empty($cellVal['filename']) || !empty($cellVal['path'])))
                                                                                @php $cellName = $cellVal['filename'] ?? 'Uploaded file'; @endphp
                                                                                @if(!empty($cellVal['url']))
                                                                                    <a href="{{ $cellVal['url'] }}" target="_blank" class="submitted-answers-file-link">
                                                                                        <i class="bi bi-paperclip"></i> {{ $cellName }}
                                                                                    </a>
                                                                                @else
                                                                                    <span><i class="bi bi-paperclip"></i> {{ $cellName }}</span>
                                                                                @endif
                                                                            @else
                                                                                <span class="text-muted">&mdash;</span>
                                                                            @endif
                                                                        @elseif(($col['type'] ?? null) === 'checkbox')
                                                                            @php
                                                                                $cellArr = is_array($cellVal) ? $cellVal : [];
                                                                                $cellLabels = collect($cellArr)->map(function ($v) use ($col) {
                                                                                    $opt = collect($col['options'] ?? [])->firstWhere('value', $v);
                                                                                    return $opt['label'] ?? $v;
                                                                                });
                                                                            @endphp
                                                                            @if($cellLabels->count())
                                                                                <div class="d-flex flex-wrap gap-1">
                                                                                    @foreach($cellLabels as $label)
                                                                                        <span class="badge bg-light text-dark border">{{ $label }}</span>
                                                                                    @endforeach
                                                                                </div>
                                                                            @else
                                                                                <span class="text-muted">&mdash;</span>
                                                                            @endif
                                                                        @elseif(($col['type'] ?? null) === 'select' && !empty($col['options']))
                                                                            @php
                                                                                $cellOpt = collect($col['options'])->firstWhere('value', $cellVal);
                                                                                $cellText = $cellOpt['label'] ?? (is_scalar($cellVal) ? (string) $cellVal : '');
                                                                            @endphp
                                                                            {{ $cellText !== '' ? $cellText : '—' }}
                                                                        @else
                                                                            @php $cellText = is_scalar($cellVal) ? (string) $cellVal : ''; @endphp
                                                                            {{ $cellText !== '' ? $cellText : '—' }}
                                                                        @endif
                                                                    </td>
                                                                @endforeach
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    @else
                                        {{ $val ?: '—' }}
                                    @endif
                                </td>
                                <td class="submitted-answers-actions">
                                    <button type="button"
                                        class="btn-request-change {{ $hasPendingRequest ? 'has-pending' : '' }}"
                                        title="{{ $hasPendingRequest ? 'Change already requested' : 'Request change' }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#requestChangeModal"
                                        data-answer-id="{{ $answer->id }}"
                                        data-question-label="{{ $question->label }}"
                                        data-action-url="{{ route('admin.user-onboardings.answers.request-change', [$userOnboarding, $answer]) }}">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    @if(($answer->audit_logs_count ?? 0) > 0)
                                        <a href="{{ route('admin.user-onboardings.answers.history', [$userOnboarding, $answer]) }}"
                                           class="submitted-answers-history-link"
                                           title="{{ $answer->audit_logs_count }} edit(s)">
                                            <i class="bi bi-clock-history"></i>
                                            <span class="history-badge">{{ $answer->audit_logs_count }}</span>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                No answers submitted yet.
            </div>
        @endforelse
    </div>
</div>

{{-- Documents — every file the client uploaded, in one place, with a reviewer
     verdict per file. Grouped by the section the file belongs to. --}}
@php
    $docAnswers = $userOnboarding->answers
        ->filter(fn($a) => $a->question && $a->question->type === 'file' && $a->files->count())
        ->sortBy([
            fn($a) => $a->question->group->order ?? 0,
            fn($a) => $a->question->order ?? 0,
        ]);
    $allFiles = $docAnswers->flatMap(fn($a) => $a->files);
    $docDecisionBadges = [
        'verified' => ['Verified', 'bg-success-subtle text-success border'],
        'rejected' => ['Rejected', 'bg-danger-subtle text-danger border'],
        'resubmit_requested' => ['Resubmission requested', 'bg-warning-subtle text-warning-emphasis border'],
    ];
    $verifiedCount = $allFiles->where('review_decision', 'verified')->count();

    // Files uploaded outside a standalone file question — inside a table cell
    // or in reply to an admin follow-up question. They aren't AnswerFile rows,
    // so they're listed read-only (no per-file verdict), but nothing a client
    // uploaded stays hidden from the reviewer.
    $otherUploads = collect();
    foreach ($userOnboarding->answers as $a) {
        if (($a->question->type ?? null) !== 'table') {
            continue;
        }
        $rows = is_string($a->value) ? json_decode($a->value, true) : ($a->value ?? []);
        $cols = $a->question->options['columns'] ?? [];
        foreach ((array) $rows as $ri => $row) {
            foreach ($cols as $col) {
                if (($col['type'] ?? null) !== 'file') {
                    continue;
                }
                $cell = $row[$col['key']] ?? null;
                if (is_array($cell) && (! empty($cell['filename']) || ! empty($cell['path']))) {
                    $otherUploads->push((object) [
                        'label' => ($a->question->label ?? 'Table') . ' — ' . ($col['label'] ?? 'File') . ' (row ' . ($ri + 1) . ')',
                        'name' => $cell['filename'] ?? 'Uploaded file',
                        'url' => $cell['url'] ?? null,
                    ]);
                }
            }
        }
    }
    foreach ($adminQuestions as $aq) {
        foreach (optional($aq->answer)->files ?? [] as $f) {
            $otherUploads->push((object) [
                'label' => 'Follow-up question: ' . ($aq->label ?? 'Admin question'),
                'name' => $f->original_filename,
                'url' => $f->url,
            ]);
        }
    }
@endphp
<div class="card mb-4" id="documents">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Documents</span>
        <span class="badge bg-secondary">{{ $verifiedCount }}/{{ $allFiles->count() }} verified{{ $otherUploads->isNotEmpty() ? ' · ' . $otherUploads->count() . ' other' : '' }}</span>
    </div>
    <div class="card-body">
        @forelse($docAnswers->groupBy(fn($a) => $a->question->group->id) as $groupId => $groupAnswers)
            @php $docGroupName = $groupAnswers->first()->question->group->name; @endphp
            <div class="{{ !$loop->first ? 'mt-4' : '' }}">
                <div class="submitted-answers-section-label">{{ $docGroupName }}</div>
                @foreach($groupAnswers as $answer)
                    @foreach($answer->files as $file)
                        @php
                            $decision = $file->review_decision;
                            $decisionMeta = $decision ? ($docDecisionBadges[$decision] ?? null) : null;
                        @endphp
                        <div class="document-row border rounded p-2 mb-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <div class="text-muted small">{{ $answer->question->label }}</div>
                                    <a href="{{ route('admin.documents.show', $file) }}" target="_blank" class="submitted-answers-file-link" title="Preview in a new tab">
                                        <i class="bi bi-paperclip"></i>
                                        {{ $file->original_filename }}
                                        <small class="text-muted ms-1">({{ $file->file_size < 1048576 ? number_format($file->file_size / 1024, 1) . ' KB' : number_format($file->file_size / 1048576, 1) . ' MB' }})</small>
                                    </a>
                                    <a href="{{ route('admin.documents.show', [$file, 'download' => 1]) }}" class="text-muted ms-1 small text-decoration-none" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    @switch($file->validation_status)
                                        @case('passed')
                                            <span class="badge bg-success-subtle text-success border ms-1" title="{{ $file->validation_summary }}">AI verified</span>
                                            @break
                                        @case('needs_review')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border ms-1" title="{{ $file->validation_summary }}">Needs review</span>
                                            @break
                                        @case('type_mismatch')
                                        @case('expired')
                                        @case('stale')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border ms-1" title="{{ $file->validation_summary }}">{{ ['type_mismatch' => 'Wrong document type', 'expired' => 'Expired', 'stale' => 'Outdated'][$file->validation_status] }} — justified</span>
                                            @break
                                    @endswitch
                                    @if($decisionMeta)
                                        <span class="badge {{ $decisionMeta[1] }} ms-1">{{ $decisionMeta[0] }}</span>
                                    @endif
                                </div>
                                @if($canReview)
                                    <form method="POST" action="{{ route('admin.user-onboardings.documents.review', [$userOnboarding, $file]) }}" class="document-review-form d-flex align-items-center gap-1">
                                        @csrf
                                        <input type="text" name="review_note" class="form-control form-control-sm" placeholder="Note (optional)" value="{{ $file->review_note }}" style="max-width: 180px;">
                                        {{-- Once verified, the Verify action is disabled — its state is
                                             already final; Reject / Request-new remain to change the verdict. --}}
                                        <button type="submit" name="review_decision" value="verified" class="btn btn-sm {{ $decision === 'verified' ? 'btn-success' : 'btn-outline-success' }}" title="{{ $decision === 'verified' ? 'Already verified' : 'Verify' }}" @disabled($decision === 'verified')><i class="bi bi-check-lg"></i></button>
                                        <button type="submit" name="review_decision" value="rejected" class="btn btn-sm {{ $decision === 'rejected' ? 'btn-danger' : 'btn-outline-danger' }}" title="Reject" @disabled($decision === 'rejected')><i class="bi bi-x-lg"></i></button>
                                        <button type="submit" name="review_decision" value="resubmit_requested" class="btn btn-sm {{ $decision === 'resubmit_requested' ? 'btn-warning' : 'btn-outline-warning' }}" title="Request new" @disabled($decision === 'resubmit_requested')><i class="bi bi-arrow-repeat"></i></button>
                                    </form>
                                @endif
                            </div>
                            @if($file->review_note)
                                <div class="small text-muted fst-italic mt-1"><i class="bi bi-chat-left-text"></i> {{ $file->review_note }}</div>
                            @endif
                            @if($file->reviewed_at && $decision)
                                <div class="small text-muted mt-1">{{ $file->reviewer?->name ? $file->reviewer->name . ' · ' : '' }}{{ $file->reviewed_at->diffForHumans() }}</div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        @empty
            @if($otherUploads->isEmpty())
                <div class="text-center text-muted py-4">
                    <i class="bi bi-folder2-open" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                    No documents uploaded.
                </div>
            @endif
        @endforelse

        {{-- Uploads that live in a table cell or a follow-up answer — listed so
             nothing a client sent is hidden, though they carry no verdict. --}}
        @if($otherUploads->isNotEmpty())
            <div class="{{ $docAnswers->isNotEmpty() ? 'mt-4' : '' }}">
                <div class="submitted-answers-section-label">Other uploads <span class="text-muted fw-normal">(read-only)</span></div>
                @foreach($otherUploads as $u)
                    <div class="document-row border rounded p-2 mb-2">
                        <div class="text-muted small">{{ $u->label }}</div>
                        @if($u->url)
                            <a href="{{ $u->url }}" target="_blank" class="submitted-answers-file-link">
                                <i class="bi bi-paperclip"></i> {{ $u->name }}
                            </a>
                        @else
                            <span><i class="bi bi-paperclip"></i> {{ $u->name }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Admin Notifications & Questions Section --}}
@if($notifications->count() > 0 || $adminQuestions->count() > 0)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Admin Actions & Notifications</span>
        <span class="badge bg-secondary">{{ $notifications->count() }} notification(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Details</th>
                        <th>Admin Message</th>
                        <th>User Response</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($notifications as $notif)
                        <tr>
                            <td>
                                @if($notif->type === 'change_request')
                                    <span class="badge bg-warning text-dark">Change Request</span>
                                @else
                                    <span class="badge bg-info text-dark">New Question</span>
                                @endif
                            </td>
                            <td style="max-width: 200px;">
                                @if($notif->type === 'change_request' && $notif->userAnswer && $notif->userAnswer->question)
                                    {{-- Show the parent section so the reviewer knows which part of
                                         the application needs updating, not just the question (EOP-75). --}}
                                    @if($notif->userAnswer->question->group)
                                        <div class="text-muted small text-uppercase" style="letter-spacing: .03em;">
                                            <i class="bi bi-folder2"></i> {{ $notif->userAnswer->question->group->name }}
                                        </div>
                                    @endif
                                    <strong>{{ $notif->userAnswer->question->label }}</strong>
                                @elseif($notif->type === 'new_question' && $notif->adminQuestion)
                                    <div class="text-muted small text-uppercase" style="letter-spacing: .03em;">
                                        <i class="bi bi-patch-question"></i>
                                        {{-- Name the section when the admin chose one (EOP-95). --}}
                                        {{ $notif->adminQuestion->group->name ?? 'Follow-up question' }}
                                    </div>
                                    <strong>{{ $notif->adminQuestion->label }}</strong>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td style="max-width: 250px;">
                                <small>{{ Str::limit($notif->message, 100) }}</small>
                            </td>
                            <td style="max-width: 200px;">
                                @if($notif->status === 'resolved')
                                    @if($notif->type === 'change_request' && $notif->userAnswer)
                                        <small class="text-success">{{ Str::limit($notif->userAnswer->value, 80) }}</small>
                                    @elseif($notif->type === 'new_question' && $notif->adminQuestion && $notif->adminQuestion->answer)
                                        <small class="text-success">{{ Str::limit($notif->adminQuestion->answer->value, 80) }}</small>
                                    @endif
                                @else
                                    <small class="text-muted">Awaiting response</small>
                                @endif
                            </td>
                            <td>
                                <span class="notification-status-badge {{ $notif->status }}">
                                    {{ ucfirst($notif->status) }}
                                </span>
                                @if($notif->status === 'resolved')
                                    @if($notif->checked_at)
                                        <span class="badge bg-success-subtle text-success border"
                                              title="Checked by {{ $notif->checkedBy->name ?? 'admin' }} on {{ $notif->checked_at->format('M d, Y H:i') }}">
                                            Checked
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border">Needs check</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <small>{{ $notif->created_at->format('M d, H:i') }}</small>
                                <br><small class="text-muted">by {{ $notif->admin->name ?? $notif->admin->email }}</small>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    @if($notif->status === 'resolved' && !$notif->checked_at)
                                        <form method="POST" action="{{ route('admin.notifications.check', $notif) }}">
                                            @csrf
                                            <input type="hidden" name="redirect_to" value="{{ route('admin.user-onboardings.show', $userOnboarding) }}">
                                            <button class="btn btn-sm btn-outline-success btn-action" title="Mark this response as checked">
                                                <i class="bi bi-check2"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-action"
                                        data-bs-toggle="modal"
                                        data-bs-target="#sendEmailModal"
                                        data-user-id="{{ $notif->user_id }}"
                                        data-notification-id="{{ $notif->id }}"
                                        data-email-type="{{ $notif->type }}"
                                        data-question-label="{{ $notif->type === 'change_request' ? ($notif->userAnswer->question->label ?? '') : ($notif->adminQuestion->label ?? '') }}"
                                        title="Send email to user">
                                        <i class="bi bi-envelope"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Reject Application Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.user-onboardings.reject', $userOnboarding) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Reject Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Rejecting <strong>{{ $userOnboarding->reference }}</strong> —
                        {{ $userOnboarding->user->name ?? $userOnboarding->user->email ?? 'client' }}.
                        The reason below is emailed to the client and shown in their portal.
                    </p>
                    <div class="mb-0">
                        <label for="rejectComment" class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectComment" name="comment" rows="4" required
                            placeholder="Explain why the application cannot be approved..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Escalate to Compliance Modal --}}
@if($userOnboarding->status === 'completed' && auth('admin')->user()?->hasAbility(\App\Enums\Ability::ESCALATE_ONBOARDING))
<div class="modal fade" id="escalateModal" tabindex="-1" aria-labelledby="escalateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.user-onboardings.escalate', $userOnboarding) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="escalateModalLabel">Escalate to Compliance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Refer <strong>{{ $userOnboarding->reference }}</strong> to compliance for the decision.
                        It stays in the review queue, flagged for the compliance team.
                    </p>
                    <div class="mb-0">
                        <label for="escalateComment" class="form-label">Note <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control" id="escalateComment" name="comment" rows="3"
                            placeholder="Why does this need compliance review?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Escalate</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Request Change Modal --}}
<div class="modal fade" id="requestChangeModal" tabindex="-1" aria-labelledby="requestChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="requestChangeForm" method="POST" action="">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requestChangeModalLabel">Request Change</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Requesting change for: <strong id="rcQuestionLabel"></strong>
                    </p>
                    <div class="mb-3">
                        <label for="rcMessage" class="form-label">Message to User <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rcMessage" name="message" rows="4" required
                            placeholder="Explain what needs to be changed..."></textarea>
                    </div>
                    <p class="text-muted small mb-2">
                        <i class="bi bi-envelope-check"></i>
                        An email notification with a "View Review" link is sent to the user automatically.
                    </p>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="rcSendEmail">
                        <label class="form-check-label" for="rcSendEmail">Customize the email</label>
                    </div>
                    <div id="rcEmailFields" style="display: none;">
                        <div class="mb-3">
                            <label for="rcEmailSubject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control" id="rcEmailSubject" name="email_subject">
                        </div>
                        <div class="mb-3">
                            <label for="rcEmailBody" class="form-label">Email Body</label>
                            <textarea class="form-control" id="rcEmailBody" name="email_body" rows="5"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Send Request</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Send Email Modal --}}
<div class="modal fade" id="sendEmailModal" tabindex="-1" aria-labelledby="sendEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('admin.send-email') }}">
            @csrf
            <input type="hidden" name="user_id" id="seUserId">
            <input type="hidden" name="notification_id" id="seNotificationId">
            <input type="hidden" name="redirect_to" value="{{ route('admin.user-onboardings.show', $userOnboarding) }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sendEmailModalLabel">Send Email to User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="seSubject" class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="seSubject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="seBody" class="form-label">Body <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="seBody" name="body" rows="8" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Send Email</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Request Change Modal
    document.getElementById('requestChangeModal').addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var answerId = button.getAttribute('data-answer-id');
        var questionLabel = button.getAttribute('data-question-label');
        var actionUrl = button.getAttribute('data-action-url');

        document.getElementById('rcQuestionLabel').textContent = questionLabel;
        document.getElementById('requestChangeForm').action = actionUrl;
        document.getElementById('rcMessage').value = '';
        document.getElementById('rcSendEmail').checked = false;
        document.getElementById('rcEmailFields').style.display = 'none';
        document.getElementById('rcEmailSubject').value = '';
        document.getElementById('rcEmailBody').value = '';
    });

    // Toggle email customization fields (empty fields = server defaults)
    document.getElementById('rcSendEmail').addEventListener('change', function () {
        document.getElementById('rcEmailFields').style.display = this.checked ? 'block' : 'none';
        if (this.checked) {
            var questionLabel = document.getElementById('rcQuestionLabel').textContent;
            document.getElementById('rcEmailSubject').value = 'Action Required: Please Update Your Response - ' + questionLabel;
            document.getElementById('rcEmailBody').value = 'Hello,\n\nWe have reviewed your onboarding submission and require some changes to one of your answers.\n\nQuestion: ' + questionLabel + '\n\nPlease log in to your account to review the details and submit your updated response.\n\nThank you,\nEficyent Team';
        } else {
            document.getElementById('rcEmailSubject').value = '';
            document.getElementById('rcEmailBody').value = '';
        }
    });

    // Send Email Modal
    document.getElementById('sendEmailModal').addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var userId = button.getAttribute('data-user-id');
        var notificationId = button.getAttribute('data-notification-id');
        var emailType = button.getAttribute('data-email-type');
        var questionLabel = button.getAttribute('data-question-label');

        document.getElementById('seUserId').value = userId;
        document.getElementById('seNotificationId').value = notificationId || '';

        if (emailType === 'change_request') {
            document.getElementById('seSubject').value = 'Action Required: Please Update Your Response - ' + questionLabel;
            document.getElementById('seBody').value = 'Hello,\n\nWe have reviewed your onboarding submission and require some changes to one of your answers.\n\nQuestion: ' + questionLabel + '\n\nPlease log in to your account to review the details and submit your updated response.\n\nThank you,\nEficyent Team';
        } else {
            document.getElementById('seSubject').value = 'New Question Assigned to You - ' + questionLabel;
            document.getElementById('seBody').value = 'Hello,\n\nA new question has been assigned to you that requires your response.\n\nQuestion: ' + questionLabel + '\n\nPlease log in to your account to provide your answer.\n\nThank you,\nEficyent Team';
        }
    });

    // Save a section review in place (AJAX) so the page doesn't reload and
    // flash to the top before scrolling back down to the section (EOP-68).
    (function () {
        var SECTION_BADGE = {
            pending: ['Not started', 'bg-light text-muted border'],
            in_progress: ['In review', 'bg-info-subtle text-info-emphasis border'],
            completed: ['Reviewed', 'bg-success-subtle text-success border'],
        };
        var meta = document.querySelector('meta[name="csrf-token"]');
        var csrf = meta ? meta.getAttribute('content') : '';

        function toast(message, ok) {
            var t = document.createElement('div');
            t.className = 'position-fixed top-0 start-50 translate-middle-x mt-3 px-3 py-2 rounded shadow-sm ' +
                (ok ? 'bg-success text-white' : 'bg-danger text-white');
            t.style.zIndex = '1080';
            t.style.fontSize = '0.9rem';
            t.textContent = message;
            document.body.appendChild(t);
            setTimeout(function () { t.remove(); }, 3000);
        }

        function updateProgress(progress) {
            var box = document.getElementById('section-review-progress');
            if (!box || !progress) return;
            var total = progress.total || 0, done = progress.done || 0;
            var pct = total ? Math.round(done / total * 100) : 0;
            var text = box.querySelector('.js-progress-text');
            if (text) text.textContent = done + ' of ' + total + ' sections reviewed';
            var bar = box.querySelector('.js-progress-bar');
            if (bar) { bar.style.width = pct + '%'; bar.classList.toggle('bg-success', !!progress.complete); }
            var done_note = box.querySelector('.js-progress-complete');
            if (done_note) done_note.style.display = progress.complete ? '' : 'none';
            box.setAttribute('data-complete', progress.complete ? '1' : '0');
        }

        document.querySelectorAll('.section-review-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = form.querySelector('button[type="submit"]');
                var section = form.closest('.review-section');
                var box = document.getElementById('section-review-progress');
                var wasComplete = box ? box.getAttribute('data-complete') === '1' : false;
                if (btn) btn.disabled = true;

                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                }).then(function (res) {
                    return res.json().then(function (data) { return { ok: res.ok, data: data }; });
                }).then(function (r) {
                    if (btn) btn.disabled = false;
                    if (!r.ok) { toast(r.data.message || 'Could not save the section.', false); return; }
                    var data = r.data;

                    if (section) {
                        section.classList.remove('status-pending', 'status-in_progress', 'status-completed');
                        section.classList.add('status-' + data.status);
                        var badge = section.querySelector('.js-section-badge');
                        if (badge && SECTION_BADGE[data.status]) {
                            badge.className = 'badge ms-1 align-middle js-section-badge ' + SECTION_BADGE[data.status][1];
                            badge.textContent = SECTION_BADGE[data.status][0];
                        }
                        var check = section.querySelector('.js-section-check');
                        if (check) check.style.display = data.status === 'completed' ? '' : 'none';
                        var metaBox = section.querySelector('.js-section-reviewed-meta');
                        var metaText = section.querySelector('.js-section-reviewed-text');
                        if (metaBox && metaText) {
                            if (data.status === 'completed' && data.reviewed_at) {
                                metaText.textContent = 'Reviewed ' + data.reviewed_at + (data.reviewer ? ' by ' + data.reviewer : '');
                                metaBox.style.display = '';
                            } else {
                                metaBox.style.display = 'none';
                            }
                        }
                    }

                    updateProgress(data.progress);
                    toast(data.message || 'Saved.', true);

                    // The Submit/Approve buttons only unlock once every section is
                    // reviewed. When that overall state flips, sync them with one
                    // navigation back to this section anchor (rare — last section).
                    if (data.progress && !!data.progress.complete !== wasComplete && section) {
                        window.location.hash = section.id;
                        window.location.reload();
                    }
                }).catch(function () {
                    if (btn) btn.disabled = false;
                    toast('Could not save the section.', false);
                });
            });
        });
    })();
</script>
@endpush
