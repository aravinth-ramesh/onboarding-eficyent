@extends('admin.layouts.app')

@section('title', 'Answer Edit History')

@push('styles')
<style>
    .answer-history-question {
        background: #f8f9fa;
        border: 1px solid #e1e5eb;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }
    .answer-history-question .question-group {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--color-accent, #2e86de);
        margin-bottom: 4px;
    }
    .answer-history-question .question-label {
        font-size: 1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }
    .answer-history-question .current-value {
        font-size: 0.875rem;
        color: #495057;
    }
    .answer-history-question .current-value strong {
        color: #6c757d;
        font-weight: 500;
    }
    .timeline {
        position: relative;
        padding-left: 28px;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 11px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e1e5eb;
    }
    .timeline-item {
        position: relative;
        padding-bottom: 1.5rem;
    }
    .timeline-item:last-child {
        padding-bottom: 0;
    }
    .timeline-dot {
        position: absolute;
        left: -28px;
        top: 4px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid var(--color-accent, #2e86de);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
    }
    .timeline-dot i {
        font-size: 0.7rem;
        color: var(--color-accent, #2e86de);
    }
    .timeline-card {
        background: #fff;
        border: 1px solid #e1e5eb;
        border-radius: 8px;
        padding: 1rem 1.25rem;
    }
    .timeline-meta {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
        font-size: 0.8rem;
        color: #6c757d;
    }
    .timeline-meta .editor {
        font-weight: 600;
        color: #2c3e50;
    }
    .timeline-diff {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .timeline-diff-row {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        font-size: 0.875rem;
    }
    .timeline-diff-row .diff-label {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
    }
    .diff-old .diff-label {
        background: #fce4ec;
        color: #c62828;
    }
    .diff-new .diff-label {
        background: #e8f5e9;
        color: #2e7d32;
    }
    .diff-old .diff-text {
        color: #c62828;
        background: #fce4ec;
        border-radius: 4px;
        padding: 4px 8px;
        word-break: break-word;
        flex: 1;
    }
    .diff-new .diff-text {
        color: #2e7d32;
        background: #e8f5e9;
        border-radius: 4px;
        padding: 4px 8px;
        word-break: break-word;
        flex: 1;
    }
    .timeline-reason {
        margin-top: 0.5rem;
        font-size: 0.8rem;
        color: #6c757d;
        font-style: italic;
    }
</style>
@endpush

@section('actions')
    <a href="{{ route('admin.user-onboardings.show', $userOnboarding) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Onboarding
    </a>
@endsection

@section('content')
{{-- Question & Current Value --}}
<div class="answer-history-question">
    <div class="question-group">{{ $answer->question->group->name ?? 'N/A' }}</div>
    <div class="question-label">{{ $answer->question->label ?? 'N/A' }}</div>
    <div class="current-value">
        <strong>Current answer:</strong>
        @if(($answer->question->type ?? '') === 'file' && $answer->files->count())
            @foreach($answer->files as $file)
                <a href="{{ $file->url }}" target="_blank" class="text-decoration-none">
                    <i class="bi bi-paperclip"></i> {{ $file->original_filename }}
                </a>{{ !$loop->last ? ', ' : '' }}
            @endforeach
        @else
            {{ $answer->value ?: '—' }}
        @endif
    </div>
</div>

{{-- Timeline --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Edit History</span>
        <span class="badge bg-secondary">{{ $logs->total() }} {{ Str::plural('edit', $logs->total()) }}</span>
    </div>
    <div class="card-body">
        @forelse($logs as $log)
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot">
                        <i class="bi bi-pencil"></i>
                    </div>
                    <div class="timeline-card">
                        <div class="timeline-meta">
                            <span class="editor">{{ $log->editor->name ?? $log->editor->email ?? 'System' }}</span>
                            <span>&middot;</span>
                            <span>{{ $log->edited_at?->format('M d, Y') }}</span>
                            <span>&middot;</span>
                            <span>{{ $log->edited_at?->format('h:i A') }}</span>
                        </div>
                        <div class="timeline-diff">
                            <div class="timeline-diff-row diff-old">
                                <span class="diff-label">&minus;</span>
                                <span class="diff-text">{{ $log->old_value ?: '(empty)' }}</span>
                            </div>
                            <div class="timeline-diff-row diff-new">
                                <span class="diff-label">+</span>
                                <span class="diff-text">{{ $log->new_value ?: '(empty)' }}</span>
                            </div>
                        </div>
                        @if($log->edit_reason)
                            <div class="timeline-reason">
                                <i class="bi bi-chat-left-text"></i> {{ $log->edit_reason }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-4">
                <i class="bi bi-clock-history" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                No edit history found for this answer.
            </div>
        @endforelse
    </div>
    @if($logs->hasPages())
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
