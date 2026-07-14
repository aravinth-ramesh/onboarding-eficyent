@extends('admin.layouts.app')

@section('title', 'Email Templates')

@section('content')
<div class="card">
    <div class="card-header">
        Client-facing email wording
        <div class="text-muted" style="font-size: 0.8rem; font-weight: normal;">
            Customize the subject and text of outgoing emails. The branded layout, buttons and
            reference cards are fixed — templates control the words.
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Last edited</th>
                        <th style="width: 90px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($templates as $template)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $template->label }}</div>
                                <small class="text-muted">{{ $template->description }}</small>
                            </td>
                            <td>
                                @if($template->customized)
                                    <span class="badge bg-info-subtle text-info-emphasis border">Customized</span>
                                @else
                                    <span class="badge bg-light text-muted border">Default</span>
                                @endif
                            </td>
                            <td>
                                @if($template->override)
                                    {{ $template->override->updated_at->format('M d, Y H:i') }}
                                    <small class="text-muted">by {{ $template->override->updatedBy->name ?? 'admin' }}</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.email-templates.edit', $template->key) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
