@extends('admin.layouts.app')

@section('title', $question ? 'Edit Question' : 'Create Question')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body">
                <form method="POST"
                    action="{{ $question ? route('admin.questions.update', $question) : route('admin.questions.store') }}">
                    @csrf
                    @if($question) @method('PUT') @endif

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Label <span class="text-danger">*</span></label>
                            <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                                value="{{ old('label', $question?->label) }}" required>
                            @error('label')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Question Group <span class="text-danger">*</span></label>
                            <select name="question_group_id" class="form-select @error('question_group_id') is-invalid @enderror" required>
                                <option value="">Select Group</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}"
                                        {{ old('question_group_id', $question?->question_group_id) == $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('question_group_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                            rows="2">{{ old('description', $question?->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror" required id="questionType">
                                @foreach(['text', 'textarea', 'number', 'date', 'select', 'multi_select', 'radio', 'file'] as $type)
                                    <option value="{{ $type }}"
                                        {{ old('type', $question?->type) === $type ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $type)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Placeholder</label>
                            <input type="text" name="placeholder" class="form-control @error('placeholder') is-invalid @enderror"
                                value="{{ old('placeholder', $question?->placeholder) }}">
                            @error('placeholder')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Order</label>
                            <input type="number" name="order" class="form-control @error('order') is-invalid @enderror"
                                value="{{ old('order', $question?->order ?? 0) }}" min="0">
                            @error('order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Help Text</label>
                        <input type="text" name="help_text" class="form-control @error('help_text') is-invalid @enderror"
                            value="{{ old('help_text', $question?->help_text) }}">
                        @error('help_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="optionsField">
                        <label class="form-label fw-semibold">Options (JSON)</label>
                        <textarea name="options" class="form-control font-monospace @error('options') is-invalid @enderror"
                            rows="4" placeholder='[{"label": "Option 1", "value": "option_1"}]'>{{ old('options', $question?->options ? json_encode($question->options, JSON_PRETTY_PRINT) : '') }}</textarea>
                        <div class="form-text">Required for select, multi_select, and radio types. JSON array of objects with "label" and "value" keys.</div>
                        @error('options')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_required" value="0">
                                <input type="checkbox" name="is_required" value="1"
                                    class="form-check-input" id="is_required"
                                    {{ old('is_required', $question?->is_required ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_required">Required</label>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1"
                                    class="form-check-input" id="is_active"
                                    {{ old('is_active', $question?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    @if($question && $question->typeMappings->count())
                        <div class="card bg-light mb-3">
                            <div class="card-header py-2">Type Mappings</div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>User Type</th>
                                                <th>Subcategory</th>
                                                <th>Order</th>
                                                <th>Required</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($question->typeMappings as $mapping)
                                                <tr>
                                                    <td>{{ $userTypes->firstWhere('id', $mapping->user_type_id)?->name ?? 'N/A' }}</td>
                                                    <td>{{ $mapping->user_type_subcategory_id ?? '-' }}</td>
                                                    <td>{{ $mapping->order }}</td>
                                                    <td>{{ $mapping->is_required ? 'Yes' : 'No' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    <hr>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            {{ $question ? 'Update Question' : 'Create Question' }}
                        </button>
                        <a href="{{ route('admin.questions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Show/hide options field based on question type
    const typeSelect = document.getElementById('questionType');
    const optionsField = document.getElementById('optionsField');
    const typesWithOptions = ['select', 'multi_select', 'radio'];

    function toggleOptions() {
        optionsField.style.display = typesWithOptions.includes(typeSelect.value) ? 'block' : 'none';
    }

    typeSelect.addEventListener('change', toggleOptions);
    toggleOptions();
</script>
@endpush
