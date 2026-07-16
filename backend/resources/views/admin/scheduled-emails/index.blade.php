@extends('admin.layouts.app')

@section('title', 'Scheduled Emails')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            Scheduled Emails
            <div class="text-muted" style="font-size: 0.8rem; font-weight: normal;">
                Bulk emails composed to send later. Pending ones can be cancelled before they fire.
            </div>
        </div>
        @if($emails->total() > 0)
            <a href="{{ route('admin.scheduled-emails.export-csv') }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-filetype-csv"></i> Export CSV
            </a>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Send At</th>
                        <th>Subject</th>
                        <th>Recipients</th>
                        <th>Status</th>
                        <th>Scheduled By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emails as $email)
                        <tr>
                            <td style="white-space: nowrap;">
                                {{ $email->send_at->format('M d, Y H:i') }}
                                @if($email->status === 'pending')
                                    <div class="small text-muted">{{ $email->send_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td>{{ Str::limit($email->subject, 50) }}</td>
                            <td>{{ count($email->onboarding_ids) }} client(s)</td>
                            <td>
                                @php
                                    $tone = ['pending' => 'bg-warning-subtle text-warning-emphasis', 'sent' => 'bg-success-subtle text-success', 'cancelled' => 'bg-secondary-subtle text-secondary'][$email->status];
                                @endphp
                                <span class="badge {{ $tone }} border">{{ ucfirst($email->status) }}</span>
                                @if($email->status === 'sent' && $email->sent_count !== null)
                                    <div class="small text-muted">{{ $email->sent_count }} sent</div>
                                @endif
                            </td>
                            <td>{{ $email->admin->name ?? '—' }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary preview-btn"
                                            data-bs-toggle="modal" data-bs-target="#previewModal"
                                            data-url="{{ route('admin.scheduled-emails.preview', $email) }}">
                                        <i class="bi bi-eye"></i> Preview
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary duplicate-btn"
                                            data-bs-toggle="modal" data-bs-target="#duplicateModal"
                                            data-url="{{ route('admin.scheduled-emails.duplicate', $email) }}"
                                            data-subject="{{ $email->subject }}"
                                            data-count="{{ count($email->onboarding_ids) }}">
                                        <i class="bi bi-copy"></i> Duplicate
                                    </button>
                                    @if($email->status === 'pending')
                                        <form method="POST" action="{{ route('admin.scheduled-emails.cancel', $email) }}"
                                              onsubmit="return confirm('Cancel this scheduled email?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No scheduled emails.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($emails->hasPages())
        <div class="card-footer">{{ $emails->links() }}</div>
    @endif
</div>

{{-- Preview modal --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="text-muted px-3 py-2 border-bottom" style="font-size: 0.8rem;">
                    Rendered with the first recipient's details. Placeholders are filled per recipient when sent.
                </div>
                <iframe id="previewFrame" title="Email preview" style="width: 100%; height: 60vh; border: 0;"></iframe>
            </div>
        </div>
    </div>
</div>

{{-- Duplicate modal --}}
<div class="modal fade" id="duplicateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="duplicateForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Duplicate Scheduled Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size: 0.9rem;">
                        Creates a new pending email with the same message and recipients
                        (<span id="dupCount">0</span> client(s)) — choose when it should go out.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" id="dupSubject" readonly>
                    </div>
                    <div class="mb-0">
                        <label for="dupSendAt" class="form-label">Send at <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="dupSendAt" name="send_at" required>
                        <div class="form-text">Server clock (UTC). Must be in the future.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-copy"></i> Duplicate</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    var previewFrame = document.getElementById('previewFrame');
    document.getElementById('previewModal').addEventListener('show.bs.modal', function (event) {
        previewFrame.src = event.relatedTarget.getAttribute('data-url');
    });
    document.getElementById('previewModal').addEventListener('hidden.bs.modal', function () {
        previewFrame.src = 'about:blank';
    });

    document.getElementById('duplicateModal').addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        document.getElementById('duplicateForm').action = btn.getAttribute('data-url');
        document.getElementById('dupSubject').value = btn.getAttribute('data-subject');
        document.getElementById('dupCount').textContent = btn.getAttribute('data-count');
        document.getElementById('dupSendAt').value = '';
    });
</script>
@endpush
@endsection
