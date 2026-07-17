@extends('admin.layouts.app')

@section('title', 'User Onboardings')

@section('content')
{{-- Search & filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.user-onboardings.index') }}" class="row g-2 align-items-center" id="filterForm">
            <div class="col-md-4 col-lg-3">
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="Search name, email or reference (ONB-...)"
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3 col-lg-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="in_progress" @selected(request('status') === 'in_progress')>In Progress</option>
                    <option value="completed" @selected(request('status') === 'completed')>Awaiting Review</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <select name="user_type_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    @foreach($userTypes as $type)
                        <option value="{{ $type->id }}" @selected(request('user_type_id') == $type->id)>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <select name="assigned" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Any Assignee</option>
                    <option value="me" @selected(request('assigned') === 'me')>Assigned to me</option>
                    <option value="unassigned" @selected(request('assigned') === 'unassigned')>Unassigned</option>
                    @foreach($admins as $adminOption)
                        <option value="{{ $adminOption->id }}" @selected(request('assigned') == $adminOption->id)>
                            {{ $adminOption->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex align-items-center gap-1">
                <select name="date_field" class="form-select form-select-sm" style="width: 115px;"
                        title="Which date to filter on" onchange="if (this.form.from.value || this.form.to.value) this.form.submit()">
                    @foreach(['submitted' => 'Submitted', 'started' => 'Started', 'decided' => 'Decided'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('date_field', 'submitted') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}"
                       title="From date" style="width: 145px;">
                <span class="text-muted small">to</span>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}"
                       title="To date" style="width: 145px;">
            </div>
            <div class="col-auto">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="resubmitted" value="1"
                           id="filterResubmitted" @checked(request()->boolean('resubmitted')) onchange="this.form.submit()">
                    <label class="form-check-label small" for="filterResubmitted">Resubmissions only</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="archived" value="1"
                           id="filterArchived" @checked(request()->boolean('archived')) onchange="this.form.submit()">
                    <label class="form-check-label small" for="filterArchived">Archived</label>
                </div>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary">Search</button>
                {{-- `date_field` is a modifier, not a filter — it narrows nothing on its own. --}}
                @if(request()->hasAny(['search', 'status', 'user_type_id', 'resubmitted', 'archived', 'assigned', 'from', 'to']))
                    <a href="{{ route('admin.user-onboardings.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
                <a href="{{ route('admin.user-onboardings.export-csv', request()->query()) }}"
                   class="btn btn-sm btn-outline-success" title="Export the current view as CSV">
                    <i class="bi bi-filetype-csv"></i> Export CSV
                </a>
            </div>
        </form>

        {{-- Saved views: apply one, or save the current filters as a new one.
             Outside the filter form above — it carries its own POST forms. --}}
        <div class="d-flex gap-2 align-items-center mt-2 pt-2 border-top">
            @if($presets->isNotEmpty())
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-bookmark"></i>
                        {{ $activePresetId ? $presets->firstWhere('id', $activePresetId)->name : 'Presets' }}
                    </button>
                    <ul class="dropdown-menu" style="min-width: 260px;">
                        @foreach($presets as $preset)
                            <li class="d-flex align-items-center">
                                <a class="dropdown-item text-truncate {{ $preset->id === $activePresetId ? 'active' : '' }}"
                                   href="{{ route('admin.user-onboardings.index', $preset->filters) }}">
                                    {{ $preset->name }}
                                </a>
                                <form method="POST" class="pe-2"
                                      action="{{ route('admin.filter-presets.destroy', ['context' => 'user-onboardings', 'preset' => $preset]) }}"
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
            @if(! empty($activeFilterSummary) && ! $activePresetId)
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#savePresetModal">
                    <i class="bi bi-bookmark-plus"></i> Save preset
                </button>
            @endif
            <span class="text-muted" style="font-size: 0.8rem;">
                Saved views are private to your account.
            </span>
        </div>
    </div>
</div>

{{-- Save preset modal — the action carries the filters currently in the URL --}}
@if(! empty($activeFilterSummary) && ! $activePresetId)
<div class="modal fade" id="savePresetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.filter-presets.store', array_merge(['context' => 'user-onboardings'], request()->query())) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Save Filter Preset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size: 0.9rem;">
                        Saves the filters currently applied as a named view you can return to.
                    </p>
                    <ul class="list-unstyled mb-3" style="font-size: 0.85rem;">
                        @foreach($activeFilterSummary as $label => $value)
                            <li><span class="text-muted">{{ $label }}:</span> <span class="fw-semibold">{{ $value }}</span></li>
                        @endforeach
                    </ul>
                    <label for="presetName" class="form-label">Preset name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="presetName" name="name" maxlength="60" required
                           placeholder="e.g. My unreviewed corporates">
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

{{-- Hidden forms populated with the current selection on submit --}}
<form method="POST" action="{{ route('admin.user-onboardings.bulk-decision', request()->query()) }}" id="bulkDecisionForm" class="d-none">
    @csrf
    <input type="hidden" name="decision" id="bulkDecision">
    <input type="hidden" name="comment" id="bulkComment">
    <div id="bulkDecisionIds"></div>
</form>
<form method="POST" action="{{ route('admin.user-onboardings.bulk-email', request()->query()) }}" id="bulkEmailForm" class="d-none">
    @csrf
    <input type="hidden" name="subject" id="bulkEmailSubjectInput">
    <input type="hidden" name="body" id="bulkEmailBodyInput">
    <input type="hidden" name="send_at" id="bulkEmailSendAtInput">
    <div id="bulkEmailIds"></div>
</form>

{{-- Bulk action bar — appears when any row is ticked --}}
<div class="card mb-3" id="bulkBar" style="display: none;">
    <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
        <span class="fw-semibold" style="font-size: 0.9rem;"><span id="bulkCount">0</span> selected</span>
        <button type="button" class="btn btn-sm btn-success" id="bulkApproveBtn">
            <i class="bi bi-check-circle"></i> Approve
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bulkRejectModal">
            <i class="bi bi-x-circle"></i> Reject
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkEmailModal">
            <i class="bi bi-envelope"></i> Send Email
        </button>
        <span class="text-muted" style="font-size: 0.8rem;">Approve/Reject apply only to awaiting-review rows; email goes to every selected client.</span>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 32px;">
                            <input type="checkbox" class="form-check-input" id="bulkSelectAll" title="Select all">
                        </th>
                        <th>ID</th>
                        <th>User</th>
                        <th>User Type</th>
                        <th>Subcategory</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Started</th>
                        <th>Completed</th>
                        <th style="width: 80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($onboardings as $onboarding)
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input bulk-check"
                                       value="{{ $onboarding->id }}"
                                       data-status="{{ $onboarding->status }}"
                                       @unless($onboarding->user?->email) data-no-email="1" @endunless>
                            </td>
                            <td>{{ $onboarding->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $onboarding->user->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $onboarding->user->email ?? '' }}</small>
                            </td>
                            <td>{{ $onboarding->userType->name ?? 'N/A' }}</td>
                            <td>{{ $onboarding->subcategory->name ?? '-' }}</td>
                            <td>
                                <span class="badge badge-{{ $onboarding->status }}">
                                    {{ ucfirst(str_replace('_', ' ', $onboarding->status)) }}
                                </span>
                            </td>
                            <td>
                                @if($onboarding->assignee)
                                    {{ $onboarding->assignee->name }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $onboarding->started_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td>{{ $onboarding->completed_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td>
                                <a href="{{ route('admin.user-onboardings.show', $onboarding) }}" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">No onboardings found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($onboardings->hasPages())
        <div class="card-footer">
            {{ $onboardings->links() }}
        </div>
    @endif
</div>

{{-- Bulk Email Modal --}}
<div class="modal fade" id="bulkEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email <span id="bulkEmailCount">0</span> Selected Client(s)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" style="font-size: 0.85rem;">
                    A one-off message to the selected clients — sent regardless of their notification
                    preferences. Use <code>&#123;&#123;name&#125;&#125;</code> and
                    <code>&#123;&#123;reference&#125;&#125;</code> to personalize.
                    Clients without an email address are skipped.
                </p>
                <div class="mb-3">
                    <label for="bulkEmailSubject" class="form-label">Subject <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="bulkEmailSubject"
                           placeholder="e.g. An update on your Eficyent onboarding">
                </div>
                <div class="mb-3">
                    <label for="bulkEmailBody" class="form-label">Message <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="bulkEmailBody" rows="7"
                              placeholder="Hello &#123;&#123;name&#125;&#125;,&#10;&#10;..."></textarea>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="bulkEmailScheduleToggle">
                    <label class="form-check-label" for="bulkEmailScheduleToggle">Schedule for later</label>
                </div>
                <div class="mb-2 d-none" id="bulkEmailSendAtWrap">
                    <label for="bulkEmailSendAt" class="form-label">Send at</label>
                    <input type="datetime-local" class="form-control" id="bulkEmailSendAt">
                    <div class="form-text">Runs on the server clock (UTC). Must be in the future.</div>
                </div>
                <div class="text-danger d-none" id="bulkEmailError" style="font-size: 0.85rem;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="bulkEmailConfirm">
                    <i class="bi bi-send"></i> <span id="bulkEmailConfirmLabel">Send Email</span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Bulk Reject Modal --}}
<div class="modal fade" id="bulkRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Selected Applications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" style="font-size: 0.9rem;">
                    The reason below is emailed to <strong>every selected client</strong> and shown in their portal.
                </p>
                <label for="bulkRejectComment" class="form-label">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" id="bulkRejectComment" rows="4" required
                          placeholder="Explain why these applications cannot be approved..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="bulkRejectConfirm">Reject Applications</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var checks = document.querySelectorAll('.bulk-check');
        var bar = document.getElementById('bulkBar');
        var count = document.getElementById('bulkCount');
        var selectAll = document.getElementById('bulkSelectAll');

        function selectedChecks() {
            return Array.prototype.filter.call(checks, function (c) { return c.checked; });
        }

        function refresh() {
            var n = selectedChecks().length;
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

        // Inject the current selection as hidden ids[] inputs into a target form.
        function fillIds(container, onlyStatus) {
            container.innerHTML = '';
            var n = 0;
            selectedChecks().forEach(function (c) {
                if (onlyStatus && c.dataset.status !== onlyStatus) return;
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = c.value;
                container.appendChild(input);
                n++;
            });
            return n;
        }

        // ── Decisions (awaiting-review rows only) ──
        var decisionForm = document.getElementById('bulkDecisionForm');

        document.getElementById('bulkApproveBtn').addEventListener('click', function () {
            var n = fillIds(document.getElementById('bulkDecisionIds'), 'completed');
            if (n === 0) { alert('None of the selected applications are awaiting review.'); return; }
            if (!confirm('Approve ' + n + ' application(s) awaiting review? Each client is notified by email.')) return;
            document.getElementById('bulkDecision').value = 'approve';
            document.getElementById('bulkComment').value = '';
            decisionForm.submit();
        });

        document.getElementById('bulkRejectConfirm').addEventListener('click', function () {
            var reason = document.getElementById('bulkRejectComment').value.trim();
            if (!reason) { document.getElementById('bulkRejectComment').focus(); return; }
            var n = fillIds(document.getElementById('bulkDecisionIds'), 'completed');
            if (n === 0) { alert('None of the selected applications are awaiting review.'); return; }
            document.getElementById('bulkDecision').value = 'reject';
            document.getElementById('bulkComment').value = reason;
            decisionForm.submit();
        });

        // ── Bulk email (all selected rows) ──
        var emailModal = document.getElementById('bulkEmailModal');
        emailModal.addEventListener('show.bs.modal', function () {
            var withEmail = selectedChecks().filter(function (c) { return !c.dataset.noEmail; }).length;
            document.getElementById('bulkEmailCount').textContent = withEmail;
        });

        // Schedule toggle reveals the datetime input and relabels the button.
        var scheduleToggle = document.getElementById('bulkEmailScheduleToggle');
        scheduleToggle.addEventListener('change', function () {
            document.getElementById('bulkEmailSendAtWrap').classList.toggle('d-none', !this.checked);
            document.getElementById('bulkEmailConfirmLabel').textContent = this.checked ? 'Schedule Email' : 'Send Email';
        });

        document.getElementById('bulkEmailConfirm').addEventListener('click', function () {
            var subject = document.getElementById('bulkEmailSubject').value.trim();
            var body = document.getElementById('bulkEmailBody').value.trim();
            var scheduling = scheduleToggle.checked;
            var sendAt = document.getElementById('bulkEmailSendAt').value;
            var err = document.getElementById('bulkEmailError');

            function fail(msg) { err.textContent = msg; err.classList.remove('d-none'); }

            if (!subject || !body) { fail('Subject and message are both required.'); return; }
            if (scheduling) {
                if (!sendAt) { fail('Choose a date and time to send.'); return; }
                if (new Date(sendAt) <= new Date()) { fail('The scheduled time must be in the future.'); return; }
            }

            fillIds(document.getElementById('bulkEmailIds'), null);
            document.getElementById('bulkEmailSubjectInput').value = subject;
            document.getElementById('bulkEmailBodyInput').value = body;
            document.getElementById('bulkEmailSendAtInput').value = scheduling ? sendAt : '';
            document.getElementById('bulkEmailForm').submit();
        });
    })();
</script>
@endpush
@endsection
