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

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>IP</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td style="white-space: nowrap;">{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                            <td>{{ $log->admin->name ?? 'Unknown' }}</td>
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
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No admin activity recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
