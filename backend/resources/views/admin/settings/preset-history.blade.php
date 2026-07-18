@extends('admin.layouts.app')

@section('title', 'Preset Customization History')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            Preset Customization History
            <div class="text-muted" style="font-size: 0.8rem; font-weight: normal;">
                Your saved-view changes — pins, ordering, imports and the pin shortcut. Read-only.
            </div>
        </div>
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
                                No preset customizations yet. Save a view, pin one, or rearrange them to see them here.
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
