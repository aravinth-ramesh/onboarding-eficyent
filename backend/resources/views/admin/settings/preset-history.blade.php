@extends('admin.layouts.app')

@section('title', 'Preset Customization History')

@section('content')
@if($clearedAt)
    <div class="alert alert-secondary d-flex justify-content-between align-items-center py-2 mb-3">
        <span class="small mb-0">
            <i class="bi bi-eraser"></i>
            History cleared {{ $clearedAt->diffForHumans() }} —
            {{ $hiddenCount }} earlier {{ \Illuminate\Support\Str::plural('entry', $hiddenCount) }} hidden from this view.
        </span>
        <form method="POST" action="{{ route('admin.settings.preset-history.restore') }}" class="ms-3">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-counterclockwise"></i> Restore
            </button>
        </form>
    </div>
@endif

{{-- Filter by action --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.settings.preset-history') }}" class="row g-2 align-items-center">
            <div class="col-md-4 col-lg-3">
                <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All actions</option>
                    @foreach($actions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedAction === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary">Filter</button>
                @if($selectedAction)
                    <a href="{{ route('admin.settings.preset-history') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            Preset Customization History
            <div class="text-muted" style="font-size: 0.8rem; font-weight: normal;">
                Your saved-view changes — pins, ordering, imports and the pin shortcut. Read-only.
            </div>
        </div>
        @if($history->total() > 0)
            <div class="d-flex gap-2">
                <a href="{{ route('admin.settings.preset-history.export', request()->only('action')) }}"
                   class="btn btn-sm btn-outline-success" title="Export the current view as CSV">
                    <i class="bi bi-filetype-csv"></i> Export CSV
                </a>
                <form method="POST" action="{{ route('admin.settings.preset-history.clear') }}"
                      onsubmit="return confirm('Clear your customization history? It disappears from this view; the admin audit log is not affected.')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Clear from your view (audit log kept)">
                        <i class="bi bi-eraser"></i> Clear history
                    </button>
                </form>
            </div>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="white-space: nowrap;">When</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Page</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php $pageLabels = ['user-onboardings' => 'Onboardings', 'scheduled-emails' => 'Scheduled Emails']; @endphp
                    @forelse($history as $entry)
                        <tr class="{{ $entry['ok'] ? '' : 'text-muted' }}">
                            <td style="white-space: nowrap;">
                                {{ $entry['at']->format('M d, Y H:i') }}
                                <div class="small text-muted">{{ $entry['at']->diffForHumans() }}</div>
                            </td>
                            <td>{{ $entry['label'] }}</td>
                            <td>{{ $entry['detail'] ?? '—' }}</td>
                            <td>{{ $entry['page'] ? ($pageLabels[$entry['page']] ?? $entry['page']) : '—' }}</td>
                            <td class="text-end">
                                @unless($entry['ok'])
                                    <span class="badge bg-danger-subtle text-danger border">failed</span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                @if($selectedAction)
                                    No history for “{{ $actions[$selectedAction] }}”.
                                @else
                                    No preset customizations yet. Save a view, pin one, or rearrange them to see them here.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($history->hasPages())
        <div class="card-footer">{{ $history->links() }}</div>
    @endif
</div>
@endsection
