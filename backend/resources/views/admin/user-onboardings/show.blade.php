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
    }
    .submitted-answers-label {
        width: 35%;
        color: #6c757d;
        font-weight: 500;
    }
    .submitted-answers-value {
        color: #2c3e50;
        font-weight: 500;
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
</style>
@endpush

@section('actions')
    <div class="d-flex gap-2">
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
            <div class="card-header">User Information</div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt class="text-muted" style="font-size: 0.8rem;">Name</dt>
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
                            {{ ucfirst(str_replace('_', ' ', $userOnboarding->status)) }}
                        </span>
                        @if($userOnboarding->reopened_at)
                            <span class="badge bg-info-subtle text-info-emphasis border" title="Reopened after rejection on {{ $userOnboarding->reopened_at->format('M d, Y H:i') }}">
                                Resubmission
                            </span>
                        @endif
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
                    {{-- Awaiting decision --}}
                    <hr>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.user-onboardings.approve', $userOnboarding) }}"
                              onsubmit="return confirm('Approve this application? The client will be notified by email.')">
                            @csrf
                            <button class="btn btn-success btn-sm">
                                <i class="bi bi-check-circle"></i> Approve
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-danger btn-sm"
                                data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                    </div>
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
                            ][$log->event] ?? ['icon' => 'bi-dot', 'class' => 'text-muted', 'label' => ucfirst($log->event)];
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
                    <table class="table table-hover mb-0">
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
            {{ ucfirst(str_replace('_', ' ', $userOnboarding->status)) }}
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
        @endphp

        @forelse($grouped as $groupId => $groupAnswers)
            @php $groupName = $groupAnswers->first()->question->group->name; @endphp
            <div class="{{ !$loop->first ? 'mt-4' : '' }}">
                <div class="submitted-answers-section-label">{{ $groupName }}</div>
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
                                                    <a href="{{ $file->url }}" target="_blank" class="submitted-answers-file-link">
                                                        <i class="bi bi-paperclip"></i>
                                                        {{ $file->original_filename }}
                                                        <small class="text-muted ms-1">({{ $file->file_size < 1048576 ? number_format($file->file_size / 1024, 1) . ' KB' : number_format($file->file_size / 1048576, 1) . ' MB' }})</small>
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
                                    @elseif(in_array($type, ['radio', 'select']))
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
                                                <table class="table table-sm table-bordered mb-0" style="font-size: 0.82rem;">
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

{{-- Admin Notifications & Questions Section --}}
@if($notifications->count() > 0 || $adminQuestions->count() > 0)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Admin Actions & Notifications</span>
        <span class="badge bg-secondary">{{ $notifications->count() }} notification(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
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
                                    <strong>{{ $notif->userAnswer->question->label }}</strong>
                                @elseif($notif->type === 'new_question' && $notif->adminQuestion)
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
                            </td>
                            <td>
                                <small>{{ $notif->created_at->format('M d, H:i') }}</small>
                                <br><small class="text-muted">by {{ $notif->admin->name ?? $notif->admin->email }}</small>
                            </td>
                            <td>
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
</script>
@endpush
