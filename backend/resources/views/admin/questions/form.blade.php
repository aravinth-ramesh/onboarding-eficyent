@extends('admin.layouts.app')

@section('title', $question ? 'Edit Question' : 'Create Question')

@php
    // Build existing mappings lookup for pre-selection
    $existingMappings = [];
    if ($question && $question->typeMappings) {
        foreach ($question->typeMappings as $m) {
            $key = $m->user_type_id;
            if (!isset($existingMappings[$key])) {
                $existingMappings[$key] = [
                    'enabled' => true,
                    'order' => $m->order,
                    'is_required' => $m->is_required,
                    'subcategory_ids' => [],
                ];
            }
            if ($m->user_type_subcategory_id) {
                $existingMappings[$key]['subcategory_ids'][] = $m->user_type_subcategory_id;
            }
        }
    }
@endphp

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body">
                <form method="POST"
                    action="{{ $question ? route('admin.questions.update', $question) : route('admin.questions.store') }}">
                    @csrf
                    @if($question) @method('PUT') @endif

                    {{-- Question Details --}}
                    <h6 class="fw-bold text-uppercase text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 0.05em;">Question Details</h6>

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

                    <hr>

                    {{-- Type Mappings --}}
                    <h6 class="fw-bold text-uppercase text-muted mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">Type Mappings</h6>
                    <p class="text-muted mb-3" style="font-size: 0.8rem;">Select which user types can see this question. For types with subcategories, you can optionally restrict to specific subcategories.</p>

                    <div id="typeMappingsSection">
                        @foreach($userTypes as $idx => $ut)
                            @php
                                $mapping = $existingMappings[$ut->id] ?? null;
                                $isChecked = $mapping ? true : false;
                            @endphp

                            <div class="card mb-2 type-mapping-card" data-type-id="{{ $ut->id }}">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex align-items-center gap-3">
                                        {{-- Enable checkbox --}}
                                        <div class="form-check mb-0">
                                            <input type="checkbox"
                                                class="form-check-input type-toggle"
                                                id="type_toggle_{{ $ut->id }}"
                                                data-idx="{{ $idx }}"
                                                {{ $isChecked ? 'checked' : '' }}>
                                            <label class="form-check-label fw-semibold" for="type_toggle_{{ $ut->id }}">
                                                {{ $ut->name }}
                                            </label>
                                        </div>

                                        {{-- Hidden user_type_id (only sent when enabled) --}}
                                        <input type="hidden"
                                            name="mappings[{{ $idx }}][user_type_id]"
                                            value="{{ $ut->id }}"
                                            {{ $isChecked ? '' : 'disabled' }}
                                            class="mapping-input-{{ $idx }}">

                                        <div class="ms-auto d-flex align-items-center gap-3 mapping-options mapping-options-{{ $idx }}" style="{{ $isChecked ? '' : 'display:none' }}">
                                            {{-- Order --}}
                                            <div class="d-flex align-items-center gap-1">
                                                <label class="form-label mb-0" style="font-size: 0.75rem; white-space: nowrap;">Order:</label>
                                                <input type="number"
                                                    name="mappings[{{ $idx }}][order]"
                                                    class="form-control form-control-sm mapping-input-{{ $idx }}"
                                                    style="width: 70px;"
                                                    value="{{ $mapping['order'] ?? old('order', $question?->order ?? 0) }}"
                                                    min="0"
                                                    {{ $isChecked ? '' : 'disabled' }}>
                                            </div>

                                            {{-- Required --}}
                                            <div class="form-check mb-0">
                                                <input type="hidden"
                                                    name="mappings[{{ $idx }}][is_required]"
                                                    value="0"
                                                    class="mapping-input-{{ $idx }}"
                                                    {{ $isChecked ? '' : 'disabled' }}>
                                                <input type="checkbox"
                                                    name="mappings[{{ $idx }}][is_required]"
                                                    value="1"
                                                    class="form-check-input mapping-input-{{ $idx }}"
                                                    id="mapping_req_{{ $ut->id }}"
                                                    {{ ($mapping['is_required'] ?? false) ? 'checked' : '' }}
                                                    {{ $isChecked ? '' : 'disabled' }}>
                                                <label class="form-check-label" for="mapping_req_{{ $ut->id }}" style="font-size: 0.8rem;">Required</label>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Subcategories (if any) --}}
                                    @if($ut->has_subcategories && $ut->subcategories->count())
                                        <div class="subcategories-section subcategories-{{ $idx }} mt-2 ps-4" style="{{ $isChecked ? '' : 'display:none' }}">
                                            <div class="text-muted mb-1" style="font-size: 0.75rem;">
                                                Subcategories <span class="fst-italic">(leave unchecked to apply to all)</span>
                                            </div>
                                            <div class="d-flex flex-wrap gap-3">
                                                @foreach($ut->subcategories as $sub)
                                                    @php
                                                        $subChecked = $mapping && in_array($sub->id, $mapping['subcategory_ids']);
                                                    @endphp
                                                    <div class="form-check mb-0">
                                                        <input type="checkbox"
                                                            name="mappings[{{ $idx }}][subcategory_ids][]"
                                                            value="{{ $sub->id }}"
                                                            class="form-check-input mapping-input-{{ $idx }}"
                                                            id="sub_{{ $ut->id }}_{{ $sub->id }}"
                                                            {{ $subChecked ? 'checked' : '' }}
                                                            {{ $isChecked ? '' : 'disabled' }}>
                                                        <label class="form-check-label" for="sub_{{ $ut->id }}_{{ $sub->id }}" style="font-size: 0.8rem;">
                                                            {{ $sub->name }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

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

@push('styles')
<style>
    .type-mapping-card {
        border: 1px solid #e1e5eb;
        transition: border-color 0.15s;
    }
    .type-mapping-card.active {
        border-color: #2e86de;
        background: #f8fbff;
    }
</style>
@endpush

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

    // Type mapping toggle
    document.querySelectorAll('.type-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const idx = this.dataset.idx;
            const card = this.closest('.type-mapping-card');
            const options = card.querySelector('.mapping-options-' + idx);
            const subcats = card.querySelector('.subcategories-' + idx);
            const inputs = card.querySelectorAll('.mapping-input-' + idx);

            if (this.checked) {
                card.classList.add('active');
                if (options) options.style.display = '';
                if (subcats) subcats.style.display = '';
                inputs.forEach(function(input) { input.disabled = false; });
            } else {
                card.classList.remove('active');
                if (options) options.style.display = 'none';
                if (subcats) subcats.style.display = 'none';
                inputs.forEach(function(input) { input.disabled = true; });
            }
        });

        // Initialize state on page load
        if (toggle.checked) {
            toggle.closest('.type-mapping-card').classList.add('active');
        }
    });
</script>
@endpush
