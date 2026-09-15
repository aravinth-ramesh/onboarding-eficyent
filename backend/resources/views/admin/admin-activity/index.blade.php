@extends('admin.layouts.app')

@section('title', 'Admin Activity')

@section('content')
{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.admin-activity.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="search" name="action" class="form-control form-control-sm"
                       placeholder="Search action or path" value="{{ request('action') }}">
            </div>
            <div class="col-md-3">
                <select name="admin_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Admins</option>
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}" @selected(request('admin_id') == $admin->id)>{{ $admin->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary">Search</button>
                @if(request()->hasAny(['action', 'admin_id']))
                    <a href="{{ route('admin.admin-activity.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

@php
    // One card per admin rather than a flat row per action: following what a
    // single member of staff did meant scanning the whole page (report item 8).
    // Grouping applies to the page being shown, so the filters and pagination
    // above behave exactly as before.
    $grouped = $logs->getCollection()->groupBy(fn ($log) => $log->admin_id ?? 0);
@endphp

@forelse($grouped as $adminId => $entries)
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2 flex-wrap"
             role="button" data-bs-toggle="collapse" data-bs-target="#activity-{{ $adminId }}"
             aria-expanded="true" aria-controls="activity-{{ $adminId }}">
            <i class="bi bi-chevron-down small text-muted"></i>
            <span class="fw-semibold">{{ $entries->first()->admin->name ?? 'Unknown admin' }}</span>
            @if($entries->first()->admin?->role)
                <span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $entries->first()->admin->role->value)) }}</span>
            @endif
            <span class="badge bg-secondary-subtle text-secondary border ms-auto">
                {{ $entries->count() }} {{ Str::plural('action', $entries->count()) }}
            </span>
            <span class="text-muted small">latest {{ $entries->max('created_at')?->format('M d, Y H:i') ?? '-' }}</span>
        </div>
        <div class="collapse show" id="activity-{{ $adminId }}">
        <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Action</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>IP</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $log)
                        <tr>
                            <td style="white-space: nowrap;">{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                            <td><code style="font-size: 0.8rem;">{{ $log->action }}</code></td>
                            <td>
                                @if($log->subject_type)
                                    {{ $log->subject_type }} #{{ $log->subject_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $log->status < 400 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} border">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td><small class="text-muted">{{ $log->ip }}</small></td>
                            <td style="max-width: 280px;">
                                @if($log->payload)
                                    <details>
                                        <summary class="text-primary" style="cursor: pointer; font-size: 0.8rem;">Payload</summary>
                                        <pre class="small bg-light p-2 mt-1 mb-0" style="white-space: pre-wrap; max-height: 180px; overflow-y: auto;">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        </div>
        </div>
    </div>
@empty
    <div class="card">
        <div class="card-body text-center text-muted py-4">No admin activity recorded yet.</div>
    </div>
@endforelse

@if($logs->hasPages())
    <div class="card">
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
    </div>
@endif
@endsection
