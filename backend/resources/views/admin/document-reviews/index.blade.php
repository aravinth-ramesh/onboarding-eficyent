@extends('admin.layouts.app')

@section('title', 'Document Reviews')

@section('content')

{{-- Tuning signals: last 30 days --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Validated (30 days)</div>
                <div class="fs-3 fw-semibold">{{ $stats['total'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Auto-pass rate</div>
                <div class="fs-3 fw-semibold">{{ $stats['auto_pass_rate'] !== null ? $stats['auto_pass_rate'] . '%' : '—' }}</div>
                <div class="small text-muted">{{ $stats['passed'] }} passed automatically</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Needs review</div>
                <div class="fs-3 fw-semibold text-warning">{{ $stats['needs_review'] }}</div>
                <div class="small text-muted">{{ $stats['justified'] }} justified overrides</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Top questions needing review</div>
                @forelse($stats['top_review_questions'] as $q)
                    <div class="small d-flex justify-content-between">
                        <span class="text-truncate me-2">{{ Str::limit($q->label, 32) }}</span>
                        <span class="fw-semibold">{{ $q->total }}</span>
                    </div>
                @empty
                    <div class="small text-muted">None — dictionaries are keeping up.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Documents awaiting review</span>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All attention statuses</option>
                @foreach(['needs_review' => 'Needs review', 'type_mismatch' => 'Wrong type (justified)', 'expired' => 'Expired (justified)', 'stale' => 'Outdated (justified)'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="form-check form-check-sm text-nowrap">
                <input class="form-check-input" type="checkbox" name="show_reviewed" value="1"
                       id="showReviewed" @checked($showReviewed) onchange="this.form.submit()">
                <label class="form-check-label small" for="showReviewed">Include reviewed</label>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Question</th>
                        <th>Document</th>
                        <th>Analysis</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($files as $file)
                        <tr>
                            <td style="white-space: nowrap;">{{ $file->created_at->format('M d, H:i') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $file->answer->user->name ?? 'N/A' }}</div>
                                @if($file->answer?->onboarding)
                                    <a class="small" href="{{ route('admin.user-onboardings.show', $file->answer->onboarding) }}">View onboarding</a>
                                @endif
                            </td>
                            <td>{{ Str::limit($file->answer->question->label ?? 'N/A', 36) }}</td>
                            <td>
                                <a href="{{ $file->url }}" target="_blank"><i class="bi bi-paperclip"></i> {{ Str::limit($file->original_filename, 28) }}</a>
                                <div>
                                    <span class="badge {{ $file->validation_status === 'needs_review' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger' }} border">
                                        {{ str_replace('_', ' ', $file->validation_status) }}
                                    </span>
                                    @if($file->reviewed_at)
                                        <span class="badge bg-success-subtle text-success border">Approved by {{ $file->reviewer->name ?? 'admin' }}</span>
                                    @endif
                                </div>
                            </td>
                            <td style="max-width: 340px;">
                                @if($file->detected_type)
                                    <div class="small">Detected: <strong>{{ config("document_validation.types.{$file->detected_type}.label", $file->detected_type) }}</strong>
                                        @if($file->expiry_date) · expires {{ $file->expiry_date }} @endif
                                        @if($file->issue_date) · issued {{ $file->issue_date }} @endif
                                    </div>
                                @endif
                                @if($file->validation_summary)
                                    <div class="small text-muted">{{ Str::limit($file->validation_summary, 110) }}</div>
                                @endif
                                @if($file->justification)
                                    <div class="small fst-italic"><i class="bi bi-chat-quote"></i> {{ Str::limit($file->justification, 110) }}</div>
                                @endif
                                @if($file->extracted_excerpt)
                                    <details class="small mt-1">
                                        <summary class="text-primary" style="cursor: pointer;">Extracted text</summary>
                                        <pre class="small bg-light p-2 mt-1 mb-0" style="white-space: pre-wrap; max-height: 220px; overflow-y: auto;">{{ $file->extracted_excerpt }}</pre>
                                    </details>
                                @else
                                    <div class="small text-muted">No text could be extracted.</div>
                                @endif
                            </td>
                            <td class="text-end" style="white-space: nowrap;">
                                @unless($file->reviewed_at)
                                    <form method="POST" action="{{ route('admin.document-reviews.approve', $file) }}">
                                        @csrf
                                        <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                                        <button class="btn btn-sm btn-outline-success" title="Mark as reviewed and acceptable">
                                            <i class="bi bi-check2"></i> Approve
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nothing awaiting review.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($files->hasPages())
        <div class="card-footer">
            {{ $files->links() }}
        </div>
    @endif
</div>
@endsection
