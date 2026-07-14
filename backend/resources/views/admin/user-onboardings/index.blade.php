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
            <div class="col-auto">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="resubmitted" value="1"
                           id="filterResubmitted" @checked(request()->boolean('resubmitted')) onchange="this.form.submit()">
                    <label class="form-check-label small" for="filterResubmitted">Resubmissions only</label>
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

{{-- Bulk decision bar — appears when awaiting-review rows are ticked --}}
<form method="POST" action="{{ route('admin.user-onboardings.bulk-decision', request()->query()) }}" id="bulkForm">
    @csrf
    <input type="hidden" name="decision" id="bulkDecision">
    <input type="hidden" name="comment" id="bulkComment">
    <div class="card mb-3" id="bulkBar" style="display: none;">
        <div class="card-body py-2 d-flex align-items-center gap-3">
            <span class="fw-semibold" style="font-size: 0.9rem;"><span id="bulkCount">0</span> selected</span>
            <button type="button" class="btn btn-sm btn-success" id="bulkApproveBtn">
                <i class="bi bi-check-circle"></i> Approve Selected
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bulkRejectModal">
                <i class="bi bi-x-circle"></i> Reject Selected
            </button>
            <span class="text-muted" style="font-size: 0.8rem;">Each client is notified by email; only applications awaiting review can be decided.</span>
        </div>
    </div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 32px;">
                            <input type="checkbox" class="form-check-input" id="bulkSelectAll" title="Select all awaiting review">
                        </th>
                        <th>ID</th>
                        <th>User</th>
                        <th>User Type</th>
                        <th>Subcategory</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>Completed</th>
                        <th style="width: 80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($onboardings as $onboarding)
                        <tr>
                            <td>
                                @if($onboarding->status === 'completed')
                                    <input type="checkbox" class="form-check-input bulk-check" name="ids[]" value="{{ $onboarding->id }}">
                                @endif
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
                            <td colspan="9" class="text-center text-muted py-4">No onboardings found.</td>
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
</form>

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
        var form = document.getElementById('bulkForm');

        function refresh() {
            var selected = document.querySelectorAll('.bulk-check:checked').length;
            count.textContent = selected;
            bar.style.display = selected > 0 ? 'block' : 'none';
        }

        checks.forEach(function (c) { c.addEventListener('change', refresh); });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checks.forEach(function (c) { c.checked = selectAll.checked; });
                refresh();
            });
        }

        document.getElementById('bulkApproveBtn').addEventListener('click', function () {
            var selected = document.querySelectorAll('.bulk-check:checked').length;
            if (!confirm('Approve ' + selected + ' application(s)? Each client will be notified by email.')) return;
            document.getElementById('bulkDecision').value = 'approve';
            document.getElementById('bulkComment').value = '';
            form.submit();
        });

        document.getElementById('bulkRejectConfirm').addEventListener('click', function () {
            var reason = document.getElementById('bulkRejectComment').value.trim();
            if (!reason) { document.getElementById('bulkRejectComment').focus(); return; }
            document.getElementById('bulkDecision').value = 'reject';
            document.getElementById('bulkComment').value = reason;
            form.submit();
        });
    })();
</script>
@endpush
@endsection
