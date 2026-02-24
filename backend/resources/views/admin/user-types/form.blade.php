@extends('admin.layouts.app')

@section('title', $userType ? 'Edit User Type' : 'Create User Type')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST"
                    action="{{ $userType ? route('admin.user-types.update', $userType) : route('admin.user-types.store') }}">
                    @csrf
                    @if($userType) @method('PUT') @endif

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $userType?->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                            <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror"
                                value="{{ old('slug', $userType?->slug) }}" required>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                            rows="3">{{ old('description', $userType?->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        @if($userType)
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Order</label>
                            <input type="text" class="form-control" value="#{{ $userType->order }}" disabled>
                            <div class="form-text">Auto-assigned.</div>
                        </div>
                        @endif
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="has_subcategories" value="0">
                                <input type="checkbox" name="has_subcategories" value="1"
                                    class="form-check-input" id="has_subcategories"
                                    {{ old('has_subcategories', $userType?->has_subcategories) ? 'checked' : '' }}>
                                <label class="form-check-label" for="has_subcategories">Has Subcategories</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1"
                                    class="form-check-input" id="is_active"
                                    {{ old('is_active', $userType?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            {{ $userType ? 'Update User Type' : 'Create User Type' }}
                        </button>
                        <a href="{{ route('admin.user-types.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
