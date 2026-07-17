@extends('admin.layouts.app')

@section('title', 'Scheduled Emails')

@section('content')
@php
    // Every active filter, including a non-default sort — so "Clear all" resets
    // the view back to its natural state in one click.
    $activeFilters = collect([$status, $search, $from, $to, $sort])
        ->filter(fn ($v) => filled($v))->count();
@endphp
<div class="card mb-3">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <form method="GET" action="{{ route('admin.scheduled-emails.index') }}" class="row g-2 align-items-center flex-grow-1">
            <div class="col-md-4">
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="Search by subject" value="{{ $search }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    @foreach(['pending' => 'Pending', 'sent' => 'Sent', 'cancelled' => 'Cancelled'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex align-items-center gap-1">
                <label class="form-label mb-0 small text-muted">Send</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}" title="From (send date)" style="width: 150px;">
                <span class="text-muted small">to</span>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}" title="To (send date)" style="width: 150px;">
            </div>
            <div class="col-auto d-flex gap-2 align-items-center">
                <button class="btn btn-sm btn-primary">Search</button>
                @if($activeFilters > 0)
                    <a href="{{ route('admin.scheduled-emails.index') }}" class="btn btn-sm btn-outline-secondary">
                        Clear all
                        <span class="badge bg-secondary ms-1">{{ $activeFilters }}</span>
                    </a>
                @endif
            </div>
        </form>

        {{-- Saved views: apply one, or save the current filters as a new one.
             Outside the filter form above — it carries its own POST forms. --}}
        <div class="d-flex gap-2 align-items-center">
            @if($presets->isNotEmpty())
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-bookmark"></i>
                        {{ $activePresetId ? $presets->firstWhere('id', $activePresetId)->name : 'Presets' }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 240px;">
                        @foreach($presets as $preset)
                            <li class="d-flex align-items-center">
                                <a class="dropdown-item text-truncate {{ $preset->id === $activePresetId ? 'active' : '' }}"
                                   href="{{ route('admin.scheduled-emails.index', $preset->filters) }}">
                                    {{ $preset->name }}
                                </a>
                                <form method="POST" class="pe-2"
                                      action="{{ route('admin.filter-presets.destroy', ['context' => 'scheduled-emails', 'preset' => $preset]) }}"
                                      onsubmit="return confirm('Delete the preset &quot;{{ $preset->name }}&quot;?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0" title="Delete preset">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($activeFilters > 0 && ! $activePresetId)
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#savePresetModal">
                    <i class="bi bi-bookmark-plus"></i> Save preset
                </button>
            @endif
        </div>
    </div>
</div>

{{-- Bulk cancel — appears when pending rows are ticked --}}
<form method="POST" action="{{ route('admin.scheduled-emails.bulk-cancel', request()->only('status', 'search', 'sort', 'from', 'to')) }}" id="bulkCancelForm" class="d-none">
    @csrf
    <div id="bulkCancelIds"></div>
</form>
<div class="card mb-3" id="cancelBar" style="display: none;">
    <div class="card-body py-2 d-flex align-items-center gap-3">
        <span class="fw-semibold" style="font-size: 0.9rem;"><span id="cancelCount">0</span> selected</span>
        <button type="button" class="btn btn-sm btn-outline-danger" id="bulkCancelBtn">
            <i class="bi bi-x-circle"></i> Cancel Selected
        </button>
        <span class="text-muted" style="font-size: 0.8rem;">Only pending emails are cancellable.</span>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            Scheduled Emails
            <div class="text-muted" style="font-size: 0.8rem; font-weight: normal;">
                Bulk emails composed to send later. Pending ones can be cancelled before they fire.
            </div>
        </div>
        @if($emails->total() > 0)
            <a href="{{ route('admin.scheduled-emails.export-csv', request()->only('status', 'search', 'sort', 'from', 'to')) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-filetype-csv"></i> Export CSV
            </a>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        @php $nextSort = $sort === 'asc' ? 'desc' : 'asc'; @endphp
                        <th style="width: 32px;">
                            <input type="checkbox" class="form-check-input" id="cancelSelectAll" title="Select all pending">
                        </th>
                        <th style="white-space: nowrap;">
                            <a href="{{ route('admin.scheduled-emails.index', array_merge(request()->only('status', 'search', 'from', 'to'), ['sort' => $nextSort])) }}"
                               class="text-decoration-none text-reset">
                                Send At
                                @if($sort === 'asc')
                                    <i class="bi bi-caret-up-fill"></i>
                                @elseif($sort === 'desc')
                                    <i class="bi bi-caret-down-fill"></i>
                                @else
                                    <i class="bi bi-arrow-down-up text-muted"></i>
                                @endif
                            </a>
                        </th>
                        <th>Subject</th>
                        <th>Recipients</th>
                        <th>Status</th>
                        <th>Scheduled By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emails as $email)
                        <tr>
                            <td>
                                @if($email->status === 'pending')
                                    <input type="checkbox" class="form-check-input cancel-check" value="{{ $email->id }}">
                                @endif
                            </td>
                            <td style="white-space: nowrap;">
                                {{ $email->send_at->format('M d, Y H:i') }}
                                @if($email->status === 'pending')
                                    <div class="small text-muted">{{ $email->send_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td>{{ Str::limit($email->subject, 50) }}</td>
                            <td>{{ count($email->onboarding_ids) }} client(s)</td>
                            <td>
                                @php
                                    $tone = ['pending' => 'bg-warning-subtle text-warning-emphasis', 'sent' => 'bg-success-subtle text-success', 'cancelled' => 'bg-secondary-subtle text-secondary'][$email->status];
                                @endphp
                                <span class="badge {{ $tone }} border">{{ ucfirst($email->status) }}</span>
                                @if($email->status === 'sent' && $email->sent_count !== null)
                                    <div class="small text-muted">{{ $email->sent_count }} sent</div>
                                @endif
                            </td>
                            <td>{{ $email->admin->name ?? '—' }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary preview-btn"
                                            data-bs-toggle="modal" data-bs-target="#previewModal"
                                            data-url="{{ route('admin.scheduled-emails.preview', $email) }}">
                                        <i class="bi bi-eye"></i> Preview
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary duplicate-btn"
                                            data-bs-toggle="modal" data-bs-target="#duplicateModal"
                                            data-url="{{ route('admin.scheduled-emails.duplicate', array_merge(['scheduledEmail' => $email], request()->only('status', 'search', 'sort', 'from', 'to'))) }}"
                                            data-subject="{{ $email->subject }}"
                                            data-count="{{ count($email->onboarding_ids) }}">
                                        <i class="bi bi-copy"></i> Duplicate
                                    </button>
                                    @if($email->status === 'pending')
                                        <form method="POST" action="{{ route('admin.scheduled-emails.cancel', array_merge(['scheduledEmail' => $email], request()->only('status', 'search', 'sort', 'from', 'to'))) }}"
                                              onsubmit="return confirm('Cancel this scheduled email?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                        </form>
                                    @elseif($email->status === 'cancelled' && $email->send_at->isFuture())
                                        <form method="POST" action="{{ route('admin.scheduled-emails.restore', array_merge(['scheduledEmail' => $email], request()->only('status', 'search', 'sort', 'from', 'to'))) }}"
                                              onsubmit="return confirm('Restore this email? It will send at its scheduled time.')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-arrow-counterclockwise"></i> Restore
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No scheduled emails.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($emails->hasPages())
        <div class="card-footer">{{ $emails->links() }}</div>
    @endif
</div>

{{-- Save preset modal — the action carries the filters currently in the URL --}}
@if($activeFilters > 0 && ! $activePresetId)
<div class="modal fade" id="savePresetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.filter-presets.store', array_merge(['context' => 'scheduled-emails'], request()->only('status', 'search', 'sort', 'from', 'to'))) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Save Filter Preset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size: 0.9rem;">
                        Saves the {{ $activeFilters }} filter(s) currently applied as a named view you can
                        return to. Presets are private to your account.
                    </p>
                    @php
                        $summary = collect([
                            'Status' => $status,
                            'Subject contains' => $search,
                            'Send from' => $from,
                            'Send to' => $to,
                            'Sort' => $sort,
                        ])->filter(fn ($v) => filled($v));
                    @endphp
                    <ul class="list-unstyled mb-3" style="font-size: 0.85rem;">
                        @foreach($summary as $label => $value)
                            <li><span class="text-muted">{{ $label }}:</span> <span class="fw-semibold">{{ $value }}</span></li>
                        @endforeach
                    </ul>
                    <label for="presetName" class="form-label">Preset name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="presetName" name="name" maxlength="60" required
                           placeholder="e.g. Pending this month">
                    <div class="form-text">Re-using an existing name overwrites that preset.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-bookmark-plus"></i> Save preset</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Preview modal --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="text-muted px-3 py-2 border-bottom" style="font-size: 0.8rem;">
                    Rendered with the first recipient's details. Placeholders are filled per recipient when sent.
                </div>
                <iframe id="previewFrame" title="Email preview" style="width: 100%; height: 60vh; border: 0;"></iframe>
            </div>
        </div>
    </div>
</div>

{{-- Duplicate modal --}}
<div class="modal fade" id="duplicateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="duplicateForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Duplicate Scheduled Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size: 0.9rem;">
                        Creates a new pending email with the same message and recipients
                        (<span id="dupCount">0</span> client(s)) — choose when it should go out.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" id="dupSubject" readonly>
                    </div>
                    <div class="mb-0">
                        <label for="dupSendAt" class="form-label">Send at <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="dupSendAt" name="send_at" required>
                        <div class="form-text">Server clock (UTC). Must be in the future.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-copy"></i> Duplicate</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // ── Bulk cancel ──
    (function () {
        var checks = document.querySelectorAll('.cancel-check');
        var bar = document.getElementById('cancelBar');
        var count = document.getElementById('cancelCount');
        var selectAll = document.getElementById('cancelSelectAll');

        function selected() {
            return Array.prototype.filter.call(checks, function (c) { return c.checked; });
        }
        function refresh() {
            var n = selected().length;
            count.textContent = n;
            bar.style.display = n > 0 ? 'block' : 'none';
        }

        checks.forEach(function (c) { c.addEventListener('change', refresh); });
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checks.forEach(function (c) { c.checked = selectAll.checked; });
                refresh();
            });
        }

        document.getElementById('bulkCancelBtn').addEventListener('click', function () {
            var picked = selected();
            if (picked.length === 0) return;
            if (!confirm('Cancel ' + picked.length + ' scheduled email(s)? They will not be sent.')) return;
            var container = document.getElementById('bulkCancelIds');
            container.innerHTML = '';
            picked.forEach(function (c) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = c.value;
                container.appendChild(input);
            });
            document.getElementById('bulkCancelForm').submit();
        });
    })();

    var previewFrame = document.getElementById('previewFrame');
    document.getElementById('previewModal').addEventListener('show.bs.modal', function (event) {
        previewFrame.src = event.relatedTarget.getAttribute('data-url');
    });
    document.getElementById('previewModal').addEventListener('hidden.bs.modal', function () {
        previewFrame.src = 'about:blank';
    });

    document.getElementById('duplicateModal').addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        document.getElementById('duplicateForm').action = btn.getAttribute('data-url');
        document.getElementById('dupSubject').value = btn.getAttribute('data-subject');
        document.getElementById('dupCount').textContent = btn.getAttribute('data-count');
        document.getElementById('dupSendAt').value = '';
    });
</script>
@endpush
@endsection
