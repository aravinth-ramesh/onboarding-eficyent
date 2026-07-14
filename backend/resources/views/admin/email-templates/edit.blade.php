@extends('admin.layouts.app')

@section('title', 'Edit Email Template')

@section('actions')
    <a href="{{ route('admin.email-templates.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">{{ $definition['label'] }}</div>
            <div class="card-body">
                <p class="text-muted" style="font-size: 0.85rem;">{{ $definition['description'] }}</p>

                <form method="POST" action="{{ route('admin.email-templates.update', $key) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject</label>
                        <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
                               value="{{ old('subject', $override->subject ?? $definition['subject']) }}" required>
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Body</label>
                        <textarea name="body" rows="10" class="form-control @error('body') is-invalid @enderror" required>{{ old('body', $override->body ?? $definition['body']) }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Plain text; line breaks are kept. Use the placeholders on the right.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save Template</button>
                    </div>
                </form>

                @if($override)
                    <form method="POST" action="{{ route('admin.email-templates.reset', $key) }}" class="mt-2"
                          onsubmit="return confirm('Discard the customized wording and return to the default?')">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Default
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Placeholders</div>
            <div class="card-body py-2">
                @foreach($definition['placeholders'] as $name => $description)
                    <div class="d-flex justify-content-between py-1 {{ $loop->last ? '' : 'border-bottom' }}" style="font-size: 0.85rem;">
                        <code>{{ '{{' . $name . '}' . '}' }}</code>
                        <span class="text-muted">{{ $description }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header">Preview (sample data)</div>
            <div class="card-body" style="font-size: 0.88rem;">
                <div class="fw-semibold border-bottom pb-2 mb-2">{{ $preview['subject'] }}</div>
                <div style="white-space: pre-wrap;">{{ $preview['body'] }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
