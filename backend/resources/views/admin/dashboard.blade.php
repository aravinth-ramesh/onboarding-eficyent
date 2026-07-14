@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">{{ $stats['users'] }}</div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">{{ $stats['user_types'] }}</div>
                    <div class="stat-label">User Types</div>
                </div>
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-tags"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">{{ $stats['questions'] }}</div>
                    <div class="stat-label">Questions</div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-question-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">{{ $stats['onboardings_total'] }}</div>
                    <div class="stat-label">Total Onboardings</div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-clipboard-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4 row-cols-2 row-cols-lg-5">
    <div class="col">
        <div class="stat-card text-center">
            <div class="stat-value text-warning">{{ $stats['onboardings_pending'] }}</div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="col">
        <div class="stat-card text-center">
            <div class="stat-value text-primary">{{ $stats['onboardings_in_progress'] }}</div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
    <div class="col">
        <div class="stat-card text-center">
            <div class="stat-value text-info">{{ $stats['onboardings_completed'] }}</div>
            <div class="stat-label">Awaiting Review</div>
        </div>
    </div>
    <div class="col">
        <div class="stat-card text-center">
            <div class="stat-value text-success">{{ $stats['onboardings_approved'] }}</div>
            <div class="stat-label">Approved</div>
        </div>
    </div>
    <div class="col">
        <div class="stat-card text-center">
            <div class="stat-value text-danger">{{ $stats['onboardings_rejected'] }}</div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>
</div>

{{-- Review activity (last 30 days, from the immutable review log) --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-success">{{ $decisionStats['approved_30d'] }}</div>
                    <div class="stat-label">Approvals (30 days)</div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-danger">{{ $decisionStats['rejected_30d'] }}</div>
                    <div class="stat-label">Rejections (30 days)</div>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-x-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-primary">{{ $decisionStats['resubmissions_30d'] }}</div>
                    <div class="stat-label">Resubmissions (30 days)</div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">
                        @if($decisionStats['avg_decision_hours'] === null)
                            &mdash;
                        @elseif($decisionStats['avg_decision_hours'] < 48)
                            {{ $decisionStats['avg_decision_hours'] }}h
                        @else
                            {{ round($decisionStats['avg_decision_hours'] / 24, 1) }}d
                        @endif
                    </div>
                    <div class="stat-label">Avg. Time to Decision</div>
                </div>
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-stopwatch"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Recent Decisions</div>
            <div class="card-body py-2">
                @forelse($recentDecisions as $decision)
                    <div class="d-flex gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}" style="font-size: 0.85rem;">
                        <i class="bi {{ $decision->event === 'approved' ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}"></i>
                        <div class="flex-grow-1">
                            <a href="{{ route('admin.user-onboardings.show', $decision->onboarding) }}" class="fw-semibold">
                                {{ $decision->onboarding->user->name ?? $decision->onboarding->user->email ?? 'Client' }}
                            </a>
                            {{ $decision->event }} by {{ $decision->admin->name ?? 'admin' }}
                            <span class="text-muted">· {{ $decision->created_at->format('M d, H:i') }}</span>
                            @if($decision->comment)
                                <div class="fst-italic text-muted text-truncate" title="{{ $decision->comment }}">"{{ $decision->comment }}"</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No decisions yet.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Recent Onboardings</span>
        <a href="{{ route('admin.user-onboardings.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Started</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOnboardings as $onboarding)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $onboarding->user->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $onboarding->user->email }}</small>
                            </td>
                            <td>{{ $onboarding->userType->name ?? 'N/A' }}</td>
                            <td>
                                <span class="badge badge-{{ $onboarding->status }}">
                                    {{ ucfirst(str_replace('_', ' ', $onboarding->status)) }}
                                </span>
                            </td>
                            <td>{{ $onboarding->started_at?->format('M d, Y') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No onboardings yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
        </div>
    </div>
</div>
@endsection
