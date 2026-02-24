@extends('admin.layouts.app')

@section('title', $rule ? 'Edit Conditional Rule' : 'Create Conditional Rule')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST"
                    action="{{ $rule ? route('admin.conditional-rules.update', $rule) : route('admin.conditional-rules.store') }}">
                    @csrf
                    @if($rule) @method('PUT') @endif

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Target Question <span class="text-danger">*</span></label>
                            <select name="question_id" class="form-select select2-enable @error('question_id') is-invalid @enderror" required data-placeholder="Select Question">
                                <option value="">Select Question</option>
                                @foreach($questions as $q)
                                    <option value="{{ $q->id }}"
                                        {{ old('question_id', $rule?->question_id) == $q->id ? 'selected' : '' }}>
                                        [{{ $q->group->name ?? 'N/A' }}] {{ Str::limit($q->label, 50) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">The question to show/hide based on the condition.</div>
                            @error('question_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Parent Question <span class="text-danger">*</span></label>
                            <select name="parent_question_id" class="form-select select2-enable @error('parent_question_id') is-invalid @enderror" required data-placeholder="Select Parent Question">
                                <option value="">Select Parent Question</option>
                                @foreach($questions as $q)
                                    <option value="{{ $q->id }}"
                                        {{ old('parent_question_id', $rule?->parent_question_id) == $q->id ? 'selected' : '' }}>
                                        [{{ $q->group->name ?? 'N/A' }}] {{ Str::limit($q->label, 50) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">The question whose answer triggers the condition.</div>
                            @error('parent_question_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Comparison Type <span class="text-danger">*</span></label>
                            <select name="comparison_type" class="form-select select2-enable @error('comparison_type') is-invalid @enderror" required data-placeholder="Select Comparison">
                                @foreach(['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'in', 'not_in', 'is_empty', 'is_not_empty'] as $comp)
                                    <option value="{{ $comp }}"
                                        {{ old('comparison_type', $rule?->comparison_type) === $comp ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $comp)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('comparison_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Trigger Value</label>
                            <input type="text" name="trigger_value" class="form-control @error('trigger_value') is-invalid @enderror"
                                value="{{ old('trigger_value', $rule?->trigger_value) }}">
                            <div class="form-text">Value to compare against parent's answer.</div>
                            @error('trigger_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Action</label>
                            <select name="action" class="form-select select2-enable @error('action') is-invalid @enderror" data-placeholder="Select Action">
                                <option value="show" {{ old('action', $rule?->action) === 'show' ? 'selected' : '' }}>Show</option>
                                <option value="hide" {{ old('action', $rule?->action) === 'hide' ? 'selected' : '' }}>Hide</option>
                            </select>
                            @error('action')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Logical Operator</label>
                            <select name="logical_operator" class="form-select select2-enable @error('logical_operator') is-invalid @enderror" data-placeholder="Select Operator">
                                <option value="and" {{ old('logical_operator', $rule?->logical_operator) === 'and' ? 'selected' : '' }}>AND</option>
                                <option value="or" {{ old('logical_operator', $rule?->logical_operator) === 'or' ? 'selected' : '' }}>OR</option>
                            </select>
                            @error('logical_operator')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1"
                                    class="form-check-input" id="is_active"
                                    {{ old('is_active', $rule?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            {{ $rule ? 'Update Rule' : 'Create Rule' }}
                        </button>
                        <a href="{{ route('admin.conditional-rules.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
