@extends('admin.layouts.app')

@section('title', 'Post-Submission Changes')

@section('content')
<div class="mb-3">
    <h4 class="mb-1">Post-Submission Changes</h4>
    <p class="text-muted mb-0" style="font-size: 0.9rem;">
        Answer changes clients made <strong>after</strong> submitting for review — including edits during a
        resubmission. Draft edits before the first submission aren't recorded.
    </p>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Application</th>
                        <th>Question</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                        <th>Changed By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php $onb = $log->answer?->onboarding; @endphp
                        <tr>
                            <td style="white-space: nowrap;">{{ $log->edited_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td>
                                @if($onb)
                                    <a href="{{ route('admin.user-onboardings.show', $onb) }}" class="fw-semibold text-decoration-none">{{ $onb->reference }}</a>
                                    <span class="badge badge-{{ $onb->status }} ms-1">{{ ucfirst(str_replace('_', ' ', $onb->status)) }}</span>
                                    <div><small class="text-muted">{{ $onb->user->name ?? $onb->user->email ?? '' }}</small></div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ Str::limit($log->question->label ?? 'N/A', 40) }}</td>
                            <td><span class="text-danger">{{ Str::limit($log->old_value ?: '—', 40) }}</span></td>
                            <td><span class="text-success">{{ Str::limit($log->new_value ?: '—', 40) }}</span></td>
                            <td>
                                {{ $log->editor->name ?? $log->editor->email ?? 'System' }}
                                @if($log->editor && $log->user && $log->editor->id !== $log->user->id)
                                    <span class="badge bg-secondary-subtle text-secondary border ms-1" title="Edited by a collaborator, not the account owner">collaborator</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No post-submission changes yet — clients haven't edited anything after submitting.
                            </td>
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
