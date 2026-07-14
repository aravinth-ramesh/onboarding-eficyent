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

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
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
                            <td colspan="8" class="text-center text-muted py-4">No onboardings found.</td>
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
@endsection
