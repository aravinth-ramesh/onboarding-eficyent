@extends('admin.layouts.app')

@section('title', 'Audit Logs')

@section('content')
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Question</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                        <th>Edited By</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td style="white-space: nowrap;">{{ $log->edited_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $log->user->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $log->user->email ?? '' }}</small>
                            </td>
                            <td>{{ Str::limit($log->question->label ?? 'N/A', 40) }}</td>
                            <td>
                                <span class="text-danger">{{ Str::limit($log->old_value ?? '-', 40) }}</span>
                            </td>
                            <td>
                                <span class="text-success">{{ Str::limit($log->new_value ?? '-', 40) }}</span>
                            </td>
                            <td>{{ $log->editor->name ?? $log->editor->email ?? 'System' }}</td>
                            <td>{{ Str::limit($log->edit_reason ?? '-', 30) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No audit logs found.</td>
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
