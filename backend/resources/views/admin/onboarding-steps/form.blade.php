@extends('admin.layouts.app')

@section('title', $step ? 'Edit Onboarding Step' : 'Create Onboarding Step')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST"
                    action="{{ $step ? route('admin.onboarding-steps.update', $step) : route('admin.onboarding-steps.store') }}">
                    @csrf
                    @if($step) @method('PUT') @endif

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $step?->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                            <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror"
                                value="{{ old('slug', $step?->slug) }}" required>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                            rows="3">{{ old('description', $step?->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Component Key <span class="text-danger">*</span></label>
                            <input type="text" name="component_key" class="form-control @error('component_key') is-invalid @enderror"
                                value="{{ old('component_key', $step?->component_key) }}" required
                                placeholder="e.g. select_type, questions, kyc, review">
                            <div class="form-text">Maps to a React component in the frontend.</div>
                            @error('component_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @if($step)
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Order</label>
                            <input type="text" class="form-control" value="#{{ $step->order }}" disabled>
                            <div class="form-text">Auto-assigned.</div>
                        </div>
                        @endif
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1"
                                    class="form-check-input" id="is_active"
                                    {{ old('is_active', $step?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Config (JSON)</label>
                        <textarea name="config" class="form-control font-monospace @error('config') is-invalid @enderror"
                            rows="4" placeholder='{"key": "value"}'>{{ old('config', $step?->config ? json_encode($step->config, JSON_PRETTY_PRINT) : '') }}</textarea>
                        <div class="form-text">Optional JSON configuration passed to the frontend component.</div>
                        @error('config')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            {{ $step ? 'Update Step' : 'Create Step' }}
                        </button>
                        <a href="{{ route('admin.onboarding-steps.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
