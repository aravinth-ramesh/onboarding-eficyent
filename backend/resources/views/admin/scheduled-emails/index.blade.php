@extends('admin.layouts.app')

@section('title', 'Scheduled Emails')

@section('content')
<div class="card">
    <div class="card-header">
        Scheduled Emails
        <div class="text-muted" style="font-size: 0.8rem; font-weight: normal;">
            Bulk emails composed to send later. Pending ones can be cancelled before they fire.
        </div>
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
                                @if($email->status === 'pending')
                                    <form method="POST" action="{{ route('admin.scheduled-emails.cancel', $email) }}"
                                          onsubmit="return confirm('Cancel this scheduled email?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                    </form>
                                @endif
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
@endsection
