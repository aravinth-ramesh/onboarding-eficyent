@extends('admin.layouts.app')

@section('title', 'User Onboardings')

@section('content')
{{-- Search & filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.user-onboardings.index') }}" class="row g-2 align-items-center">
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
                @if(request()->hasAny(['search', 'status', 'user_type_id', 'resubmitted']))
                    <a href="{{ route('admin.user-onboardings.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
                <a href="{{ route('admin.user-onboardings.export-csv', request()->query()) }}"
                   class="btn btn-sm btn-outline-success" title="Export the current view as CSV">
                    <i class="bi bi-filetype-csv"></i> Export CSV
                </a>
            </div>
        </form>
    </div>
</div>

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
                <div class="mb-2">
                    <label for="bulkEmailBody" class="form-label">Message <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="bulkEmailBody" rows="7"
                              placeholder="Hello &#123;&#123;name&#125;&#125;,&#10;&#10;..."></textarea>
                </div>
                <div class="text-danger d-none" id="bulkEmailError" style="font-size: 0.85rem;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="bulkEmailConfirm">
                    <i class="bi bi-send"></i> Send Email
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

        document.getElementById('bulkEmailConfirm').addEventListener('click', function () {
            var subject = document.getElementById('bulkEmailSubject').value.trim();
            var body = document.getElementById('bulkEmailBody').value.trim();
            var err = document.getElementById('bulkEmailError');
            if (!subject || !body) {
                err.textContent = 'Subject and message are both required.';
                err.classList.remove('d-none');
                return;
            }
            fillIds(document.getElementById('bulkEmailIds'), null);
            document.getElementById('bulkEmailSubjectInput').value = subject;
            document.getElementById('bulkEmailBodyInput').value = body;
            document.getElementById('bulkEmailForm').submit();
        });
    })();
</script>
@endpush
@endsection
