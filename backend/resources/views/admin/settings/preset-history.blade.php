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

{{-- Search & filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.settings.preset-history') }}" class="row g-2 align-items-center">
            <div class="col-md-4 col-lg-3">
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="Search name, page or action" value="{{ $search }}">
            </div>
            <div class="col-md-4 col-lg-3">
                <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All actions</option>
                    @foreach($actions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedAction === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary">Search</button>
                @if($selectedAction || $search)
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
        @if($history->total() > 0 || $hasPinnedHistory)
            <div class="d-flex gap-2">
                @if($hasPinnedHistory)
                    <form method="POST" action="{{ route('admin.settings.preset-history.unpin-all') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Unpin every pinned entry">
                            <i class="bi bi-pin-angle"></i> Unpin all
                        </button>
                    </form>
                @endif
                @if($history->total() > 0)
                    <a href="{{ route('admin.settings.preset-history.export', request()->only('action', 'search')) }}"
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
                @endif
            </div>
        @endif
    </div>
    {{-- Bulk pin bar — appears when rows are ticked --}}
    <div class="card-body py-2 border-bottom d-none history-bulk-bar">
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted"><span class="history-bulk-count">0</span> selected</span>
            <button type="button" class="btn btn-sm btn-outline-warning history-bulk-pin" data-pinned="1">
                <i class="bi bi-pin-angle-fill"></i> Pin
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary history-bulk-pin" data-pinned="0">
                <i class="bi bi-pin-angle"></i> Unpin
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 32px;">
                            <input type="checkbox" class="form-check-input" id="historySelectAll" title="Select all">
                        </th>
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
                        <tr class="{{ $entry['ok'] ? '' : 'text-muted' }} {{ $entry['pinned'] ? 'table-warning' : '' }}">
                            <td>
                                <input type="checkbox" class="form-check-input history-check" value="{{ $entry['id'] }}"
                                       aria-label="Select entry">
                            </td>
                            <td style="white-space: nowrap;">
                                @if($entry['pinned'])
                                    <i class="bi bi-pin-angle-fill text-warning me-1" title="Pinned"></i>
                                @endif
                                {{ $entry['at']->format('M d, Y H:i') }}
                                <div class="small text-muted">{{ $entry['at']->diffForHumans() }}</div>
                            </td>
                            <td>{{ $entry['label'] }}</td>
                            <td>{{ $entry['detail'] ?? '—' }}</td>
                            <td>{{ $entry['page'] ? ($pageLabels[$entry['page']] ?? $entry['page']) : '—' }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end align-items-center">
                                    @unless($entry['ok'])
                                        <span class="badge bg-danger-subtle text-danger border">failed</span>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.settings.preset-history.pin', $entry['id']) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-link p-0 {{ $entry['pinned'] ? 'text-warning' : 'text-secondary' }}"
                                                title="{{ $entry['pinned'] ? 'Unpin' : 'Pin to top' }}"
                                                aria-label="{{ $entry['pinned'] ? 'Unpin entry' : 'Pin entry to top' }}">
                                            <i class="bi {{ $entry['pinned'] ? 'bi-pin-fill' : 'bi-pin-angle' }}"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                @if($search)
                                    No history matches “{{ $search }}”{{ $selectedAction ? ' for ' . $actions[$selectedAction] : '' }}.
                                @elseif($selectedAction)
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

{{-- JS collects the checked ids into this before submitting (rows carry their
     own pin forms, so no nesting). --}}
<form method="POST" action="{{ route('admin.settings.preset-history.bulk-pin') }}" id="historyBulkPinForm" class="d-none">
    @csrf
    <input type="hidden" name="pinned" id="historyBulkPinnedValue" value="1">
    <div id="historyBulkPinIds"></div>
</form>

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('historyBulkPinForm');
        if (!form) return;
        var checks = document.querySelectorAll('.history-check');
        if (checks.length === 0) return;
        var bar = document.querySelector('.history-bulk-bar');
        var count = document.querySelector('.history-bulk-count');
        var selectAll = document.getElementById('historySelectAll');
        var idsBox = document.getElementById('historyBulkPinIds');
        var pinnedValue = document.getElementById('historyBulkPinnedValue');

        function selected() {
            return Array.prototype.filter.call(checks, function (c) { return c.checked; });
        }
        function refresh() {
            var n = selected().length;
            count.textContent = n;
            bar.classList.toggle('d-none', n === 0);
        }

        checks.forEach(function (c) { c.addEventListener('change', refresh); });
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checks.forEach(function (c) { c.checked = selectAll.checked; });
                refresh();
            });
        }

        document.querySelectorAll('.history-bulk-pin').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var picked = selected();
                if (picked.length === 0) return;
                pinnedValue.value = btn.getAttribute('data-pinned');
                idsBox.innerHTML = '';
                picked.forEach(function (c) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = c.value;
                    idsBox.appendChild(input);
                });
                form.submit();
            });
        });
    })();
</script>
@endpush
@endsection
