@extends('admin.layouts.app')

@section('title', 'User Onboardings')

@section('content')
{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.user-onboardings.index') }}" class="d-flex align-items-center gap-3">
            <label class="form-label mb-0 fw-semibold" style="white-space: nowrap;">Filter by Status:</label>
            <select name="status" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach(['pending', 'in_progress', 'completed'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </option>
                @endforeach
            </select>
            @if(request('status'))
                <a href="{{ route('admin.user-onboardings.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
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
