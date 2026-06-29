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
                <form method="POST" id="questionForm"
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
                            <select name="question_group_id" class="form-select select2-enable @error('question_group_id') is-invalid @enderror" required data-placeholder="Select Group">
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
                            rows="2" placeholder="Explain what this question means or why you ask it.">{{ old('description', $question?->description) }}</textarea>
                        <div class="form-text"><i class="bi bi-info-circle"></i> Shown in an info tooltip next to the question. Use it to clarify anything a client might not understand.</div>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select select2-enable @error('type') is-invalid @enderror" required id="questionType" data-placeholder="Select Type">
                                @foreach(['text', 'textarea', 'number', 'phone', 'date', 'select', 'multi_select', 'radio', 'mcc', 'address', 'file', 'table'] as $type)
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
                            <div class="form-text">Faint example text inside the field.</div>
                            @error('placeholder')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @if($question)
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Order</label>
                            <input type="text" class="form-control" value="#{{ $question->order }}" disabled>
                            <div class="form-text">Auto-assigned on creation.</div>
                        </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Help Text</label>
                        <input type="text" name="help_text" class="form-control @error('help_text') is-invalid @enderror"
                            value="{{ old('help_text', $question?->help_text) }}"
                            placeholder="e.g. Enter the name exactly as registered.">
                        <div class="form-text">Short hint shown directly under the question (good for format or examples).</div>
                        @error('help_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Visual Options Builder (for radio, select, multi_select) --}}
                    <div class="mb-3" id="optionsField" style="display: none;">
                        <label class="form-label fw-semibold">Options <span class="text-danger">*</span></label>
                        <div class="form-text mb-2">Add label/value pairs for each option.</div>
                        <div id="optionsList"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="addOptionBtn">
                            <i class="bi bi-plus"></i> Add Option
                        </button>
                        <input type="hidden" name="options" id="optionsHidden">
                        @error('options')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Table Column Builder (for type=table) --}}
                    <div class="mb-3" id="tableColumnsBuilder" style="display: none;">
                        <label class="form-label fw-semibold">Table Columns</label>
                        <div class="form-text mb-2">Define the columns for this table question. Each column becomes a field in each row.</div>

                        <div id="tableColumnsList"></div>

                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addTableColumnBtn">
                            <i class="bi bi-plus-circle"></i> Add Column
                        </button>

                        <hr class="my-3">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" style="font-size: 0.85rem;">Min Rows</label>
                                <input type="number" class="form-control form-control-sm" id="tableMinRows" min="1" max="100" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" style="font-size: 0.85rem;">Max Rows</label>
                                <input type="number" class="form-control form-control-sm" id="tableMaxRows" min="1" max="100" value="10">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="tableAllowAddRows" checked>
                                    <label class="form-check-label" for="tableAllowAddRows">Allow users to add/remove rows</label>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="options" id="tableOptionsJson">

                        @error('options')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Validation Rules (text/textarea/number/date) --}}
                    <div class="mb-3" id="validationRulesBuilder" style="display: none;">
                        <h6 class="fw-bold text-uppercase text-muted mb-2" style="font-size: 0.75rem; letter-spacing: 0.05em;">Validation Rules</h6>
                        <div class="form-text mb-2">
                            Field-level checks applied on the client. Leave blank to skip a rule.
                        </div>

                        {{-- Text / Textarea rules --}}
                        <div class="vr-block" data-vr-types="text,textarea">
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size: 0.85rem;">Pattern Preset</label>
                                    <select class="form-select form-select-sm" id="vrPatternPreset">
                                        <option value="">— None / Custom —</option>
                                        <option value="email" data-pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$" data-message="Enter a valid email address.">Email</option>
                                        <option value="phone_in" data-pattern="^[6-9][0-9]{9}$" data-message="Enter a 10-digit Indian mobile number.">Phone (IN, 10 digits)</option>
                                        <option value="phone_intl" data-pattern="^\+?[1-9][0-9]{7,14}$" data-message="Enter a valid international phone number.">Phone (international)</option>
                                        <option value="url" data-pattern="^(https?://)[^\s/$.?#].[^\s]*$" data-message="Enter a valid URL (http:// or https://).">URL</option>
                                        <option value="alpha" data-pattern="^[A-Za-z ]+$" data-message="Only letters and spaces are allowed.">Alphabetic only</option>
                                        <option value="alphanumeric" data-pattern="^[A-Za-z0-9 ]+$" data-message="Only letters, numbers and spaces are allowed.">Alphanumeric</option>
                                        <option value="alphanumeric_special" data-pattern="^[A-Za-z0-9 .,\-_&amp;'/()]+$" data-message="Only letters, numbers, spaces and . , - _ &amp; ' / ( ) are allowed.">Alphanumeric + common special chars</option>
                                        <option value="pan" data-pattern="^[A-Z]{5}[0-9]{4}[A-Z]$" data-message="Enter a valid PAN (e.g. ABCDE1234F).">PAN (India)</option>
                                        <option value="aadhaar" data-pattern="^[2-9][0-9]{11}$" data-message="Enter a valid 12-digit Aadhaar number.">Aadhaar (India)</option>
                                        <option value="gstin" data-pattern="^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$" data-message="Enter a valid GSTIN.">GSTIN (India)</option>
                                        <option value="pincode" data-pattern="^[1-9][0-9]{5}$" data-message="Enter a valid 6-digit PIN code.">PIN code (India, 6 digits)</option>
                                        <option value="zip_us" data-pattern="^[0-9]{5}(-[0-9]{4})?$" data-message="Enter a valid US ZIP code.">ZIP (US)</option>
                                        <option value="ifsc" data-pattern="^[A-Z]{4}0[A-Z0-9]{6}$" data-message="Enter a valid IFSC code.">IFSC (India)</option>
                                        <option value="custom" data-pattern="" data-message="">Custom regex…</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold" style="font-size: 0.85rem;">Pattern (regex)</label>
                                    <input type="text" class="form-control form-control-sm" id="vrPattern" placeholder="e.g. ^[A-Za-z]+$">
                                    <div class="form-text">Anchored automatically — the entire value must match.</div>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold" style="font-size: 0.85rem;">Pattern Message Preset</label>
                                    <select class="form-select form-select-sm mb-1" id="vrMessagePreset">
                                        <option value="">— Choose a preset or type your own —</option>
                                        <option>Enter a valid email address.</option>
                                        <option>Enter a 10-digit phone number.</option>
                                        <option>Enter a valid URL.</option>
                                        <option>Only letters and spaces are allowed.</option>
                                        <option>Only letters, numbers and spaces are allowed.</option>
                                        <option>Value does not match the required format.</option>
                                    </select>
                                    <input type="text" class="form-control form-control-sm" id="vrPatternMessage" placeholder="Shown when the value doesn't match the pattern">
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size: 0.85rem;">Min Length</label>
                                    <input type="number" min="0" class="form-control form-control-sm" id="vrMinLength" placeholder="e.g. 2">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size: 0.85rem;">Max Length</label>
                                    <input type="number" min="0" class="form-control form-control-sm" id="vrMaxLength" placeholder="e.g. 100">
                                </div>
                            </div>
                        </div>

                        {{-- Number rules --}}
                        <div class="vr-block" data-vr-types="number" style="display: none;">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size: 0.85rem;">Minimum Value</label>
                                    <input type="number" step="any" class="form-control form-control-sm" id="vrMin" placeholder="e.g. 0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size: 0.85rem;">Maximum Value</label>
                                    <input type="number" step="any" class="form-control form-control-sm" id="vrMax" placeholder="e.g. 100">
                                </div>
                            </div>
                        </div>

                        {{-- Date rules --}}
                        <div class="vr-block" data-vr-types="date" style="display: none;">
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="vrAllowPast" checked>
                                        <label class="form-check-label" for="vrAllowPast">Allow past dates</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="vrAllowFuture" checked>
                                        <label class="form-check-label" for="vrAllowFuture">Allow future dates</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="vrAllowToday" checked>
                                        <label class="form-check-label" for="vrAllowToday">Allow today's date</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size: 0.85rem;">Min Date</label>
                                    <input type="date" class="form-control form-control-sm" id="vrMinDate">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size: 0.85rem;">Max Date</label>
                                    <input type="date" class="form-control form-control-sm" id="vrMaxDate">
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="validation_rules" id="validationRulesJson">
                        @error('validation_rules')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
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
        border-color: var(--color-accent, #6366F1);
        background: var(--color-accent-soft, rgba(99, 102, 241, 0.06));
    }
    .table-column-card {
        border: 1px solid #e1e5eb;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        background: #fafbfc;
        position: relative;
    }
    .table-column-card .column-number {
        position: absolute;
        top: 8px;
        left: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
    }
    .table-column-card .remove-column-btn {
        position: absolute;
        top: 6px;
        right: 8px;
    }
    .table-column-suboptions {
        margin-top: 8px;
        padding: 8px;
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 4px;
    }
    .table-column-suboptions .suboption-row {
        display: flex;
        gap: 6px;
        margin-bottom: 4px;
    }
    .option-row {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 6px;
    }
    .option-row .form-control {
        flex: 1;
    }
</style>
@endpush

@push('scripts')
<script>
    var typeSelect = document.getElementById('questionType');
    var optionsField = document.getElementById('optionsField');
    var tableBuilder = document.getElementById('tableColumnsBuilder');
    var optionsHidden = document.getElementById('optionsHidden');
    var tableOptionsJson = document.getElementById('tableOptionsJson');
    var validationBuilder = document.getElementById('validationRulesBuilder');
    var validationRulesJson = document.getElementById('validationRulesJson');
    var typesWithOptions = ['select', 'multi_select', 'radio'];
    var typesWithValidation = ['text', 'textarea', 'number', 'date'];

    // === Toggle sections based on type ===
    function toggleOptions() {
        var val = typeSelect.value;
        if (val === 'table') {
            optionsField.style.display = 'none';
            tableBuilder.style.display = 'block';
            optionsHidden.disabled = true;
            tableOptionsJson.disabled = false;
        } else if (typesWithOptions.includes(val)) {
            optionsField.style.display = 'block';
            tableBuilder.style.display = 'none';
            optionsHidden.disabled = false;
            tableOptionsJson.disabled = true;
        } else {
            optionsField.style.display = 'none';
            tableBuilder.style.display = 'none';
            optionsHidden.disabled = true;
            tableOptionsJson.disabled = true;
        }

        // Validation rules block — visible for text/textarea/number/date,
        // and the inner sub-blocks switch to match the chosen type.
        if (typesWithValidation.indexOf(val) !== -1) {
            validationBuilder.style.display = 'block';
            validationRulesJson.disabled = false;
            validationBuilder.querySelectorAll('.vr-block').forEach(function (block) {
                var supported = (block.dataset.vrTypes || '').split(',');
                block.style.display = supported.indexOf(val) !== -1 ? '' : 'none';
            });
        } else {
            validationBuilder.style.display = 'none';
            validationRulesJson.disabled = true;
        }
    }

    // Use jQuery .on('change') so Select2-triggered changes are caught
    $('#questionType').on('change', function() {
        toggleOptions();
    });

    // === Visual Options Builder (for radio, select, multi_select) ===
    var optionsList = document.getElementById('optionsList');

    function createOptionRow(label, value) {
        var row = document.createElement('div');
        row.className = 'option-row';
        row.innerHTML = '<input type="text" class="form-control form-control-sm opt-label" placeholder="Label" value="' + (label || '') + '">' +
            '<input type="text" class="form-control form-control-sm opt-value" placeholder="Value" value="' + (value || '') + '">' +
            '<button type="button" class="btn btn-sm btn-outline-danger remove-opt-btn" title="Remove"><i class="bi bi-x"></i></button>';

        // Auto-generate value from label
        var labelInput = row.querySelector('.opt-label');
        var valueInput = row.querySelector('.opt-value');
        labelInput.addEventListener('input', function() {
            if (!valueInput.dataset.manual) {
                valueInput.value = slugify(labelInput.value);
            }
        });
        valueInput.addEventListener('input', function() {
            if (this.value) valueInput.dataset.manual = '1';
            else delete valueInput.dataset.manual;
        });
        if (value) valueInput.dataset.manual = '1';

        // Remove row
        row.querySelector('.remove-opt-btn').addEventListener('click', function() {
            if (optionsList.querySelectorAll('.option-row').length > 1) {
                row.remove();
            }
        });

        optionsList.appendChild(row);
    }

    document.getElementById('addOptionBtn').addEventListener('click', function() {
        createOptionRow();
    });

    // Load existing options when editing a radio/select/multi_select question
    @if($question && in_array($question->type, ['radio', 'select', 'multi_select']) && $question->options)
    (function() {
        var existing = @json($question->options);
        if (Array.isArray(existing) && existing.length) {
            existing.forEach(function(opt) {
                createOptionRow(opt.label || '', opt.value || '');
            });
        } else {
            createOptionRow();
        }
    })();
    @else
    // Default: one empty row
    createOptionRow();
    @endif

    // === Table Column Builder ===
    var tableColumnsList = document.getElementById('tableColumnsList');
    var columnCounter = 0;

    // Shared regex / message presets used by both the top-level validation
    // dropdowns above and the per-column validation block built below. The
    // top-level dropdowns hard-code the same list in Blade; this JS copy is
    // only consumed when rendering column-level validation fields.
    var PATTERN_PRESETS = [
        { value: 'email',        label: 'Email',                       pattern: '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$', message: 'Enter a valid email address.' },
        { value: 'phone_in',     label: 'Phone (IN, 10 digits)',       pattern: '^[6-9][0-9]{9}$',                                    message: 'Enter a 10-digit Indian mobile number.' },
        { value: 'phone_intl',   label: 'Phone (international)',       pattern: '^\\+?[1-9][0-9]{7,14}$',                              message: 'Enter a valid international phone number.' },
        { value: 'url',          label: 'URL',                         pattern: '^(https?://)[^\\s/$.?#].[^\\s]*$',                    message: 'Enter a valid URL (http:// or https://).' },
        { value: 'alpha',        label: 'Alphabetic only',             pattern: '^[A-Za-z ]+$',                                       message: 'Only letters and spaces are allowed.' },
        { value: 'alphanumeric', label: 'Alphanumeric',                pattern: '^[A-Za-z0-9 ]+$',                                    message: 'Only letters, numbers and spaces are allowed.' },
        { value: 'alphanumeric_special', label: 'Alphanumeric + common special chars', pattern: "^[A-Za-z0-9 .,\\-_&'/()]+$",      message: "Only letters, numbers, spaces and . , - _ & ' / ( ) are allowed." },
        { value: 'pan',          label: 'PAN (India)',                 pattern: '^[A-Z]{5}[0-9]{4}[A-Z]$',                            message: 'Enter a valid PAN (e.g. ABCDE1234F).' },
        { value: 'aadhaar',      label: 'Aadhaar (India)',             pattern: '^[2-9][0-9]{11}$',                                   message: 'Enter a valid 12-digit Aadhaar number.' },
        { value: 'gstin',        label: 'GSTIN (India)',               pattern: '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$',  message: 'Enter a valid GSTIN.' },
        { value: 'pincode',      label: 'PIN code (India, 6 digits)',  pattern: '^[1-9][0-9]{5}$',                                    message: 'Enter a valid 6-digit PIN code.' },
        { value: 'zip_us',       label: 'ZIP (US)',                    pattern: '^[0-9]{5}(-[0-9]{4})?$',                             message: 'Enter a valid US ZIP code.' },
        { value: 'ifsc',         label: 'IFSC (India)',                pattern: '^[A-Z]{4}0[A-Z0-9]{6}$',                             message: 'Enter a valid IFSC code.' }
    ];

    var MESSAGE_PRESETS = [
        'Enter a valid email address.',
        'Enter a 10-digit phone number.',
        'Enter a valid URL.',
        'Only letters and spaces are allowed.',
        'Only letters, numbers and spaces are allowed.',
        'Value does not match the required format.'
    ];

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Build the inputs for a column's `validation` block. `colType` decides
    // which fields show; `existing` pre-populates them when editing.
    function renderColumnValidationFields(card, colType, existing) {
        var wrapper = card.querySelector('.col-validation-wrapper');
        var fieldsHost = card.querySelector('.col-validation-fields');
        var typesWithValidation = ['text', 'textarea', 'number', 'date'];
        if (typesWithValidation.indexOf(colType) === -1) {
            wrapper.style.display = 'none';
            fieldsHost.innerHTML = '';
            return;
        }
        wrapper.style.display = '';
        existing = existing || {};

        var html = '';

        if (colType === 'text' || colType === 'textarea') {
            var presetOpts = '<option value="">— None / Custom —</option>';
            PATTERN_PRESETS.forEach(function (p) {
                presetOpts += '<option value="' + escapeHtml(p.value)
                    + '" data-pattern="' + escapeHtml(p.pattern)
                    + '" data-message="' + escapeHtml(p.message) + '">'
                    + escapeHtml(p.label) + '</option>';
            });
            presetOpts += '<option value="custom" data-pattern="" data-message="">Custom regex…</option>';

            var msgOpts = '<option value="">— Choose a preset or type your own —</option>';
            MESSAGE_PRESETS.forEach(function (m) {
                msgOpts += '<option>' + escapeHtml(m) + '</option>';
            });

            html =
                '<div class="row g-2 mb-2">' +
                    '<div class="col-md-4">' +
                        '<label class="form-label" style="font-size:0.78rem;">Pattern Preset</label>' +
                        '<select class="form-select form-select-sm cv-pattern-preset">' + presetOpts + '</select>' +
                    '</div>' +
                    '<div class="col-md-8">' +
                        '<label class="form-label" style="font-size:0.78rem;">Pattern (regex)</label>' +
                        '<input type="text" class="form-control form-control-sm cv-pattern" placeholder="e.g. ^[A-Za-z]+$" value="' + escapeHtml(existing.pattern || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="row g-2 mb-2">' +
                    '<div class="col-md-12">' +
                        '<label class="form-label" style="font-size:0.78rem;">Pattern Message Preset</label>' +
                        '<select class="form-select form-select-sm cv-message-preset mb-1">' + msgOpts + '</select>' +
                        '<input type="text" class="form-control form-control-sm cv-pattern-message" placeholder="Shown when the value doesn\'t match" value="' + escapeHtml(existing.pattern_message || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="row g-2">' +
                    '<div class="col-md-6">' +
                        '<label class="form-label" style="font-size:0.78rem;">Min Length</label>' +
                        '<input type="number" min="0" class="form-control form-control-sm cv-min-length" value="' + (existing.min_length != null ? existing.min_length : '') + '">' +
                    '</div>' +
                    '<div class="col-md-6">' +
                        '<label class="form-label" style="font-size:0.78rem;">Max Length</label>' +
                        '<input type="number" min="0" class="form-control form-control-sm cv-max-length" value="' + (existing.max_length != null ? existing.max_length : '') + '">' +
                    '</div>' +
                '</div>';
        } else if (colType === 'number') {
            html =
                '<div class="row g-2">' +
                    '<div class="col-md-6">' +
                        '<label class="form-label" style="font-size:0.78rem;">Minimum Value</label>' +
                        '<input type="number" step="any" class="form-control form-control-sm cv-min" value="' + (existing.min != null ? existing.min : '') + '">' +
                    '</div>' +
                    '<div class="col-md-6">' +
                        '<label class="form-label" style="font-size:0.78rem;">Maximum Value</label>' +
                        '<input type="number" step="any" class="form-control form-control-sm cv-max" value="' + (existing.max != null ? existing.max : '') + '">' +
                    '</div>' +
                '</div>';
        } else if (colType === 'date') {
            html =
                '<div class="row g-2 mb-2">' +
                    '<div class="col-md-4">' +
                        '<div class="form-check">' +
                            '<input type="checkbox" class="form-check-input cv-allow-past"' + (existing.allow_past === false ? '' : ' checked') + '>' +
                            '<label class="form-check-label" style="font-size:0.78rem;">Allow past dates</label>' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-md-4">' +
                        '<div class="form-check">' +
                            '<input type="checkbox" class="form-check-input cv-allow-future"' + (existing.allow_future === false ? '' : ' checked') + '>' +
                            '<label class="form-check-label" style="font-size:0.78rem;">Allow future dates</label>' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-md-4">' +
                        '<div class="form-check">' +
                            '<input type="checkbox" class="form-check-input cv-allow-today"' + (existing.allow_today === false ? '' : ' checked') + '>' +
                            '<label class="form-check-label" style="font-size:0.78rem;">Allow today</label>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="row g-2">' +
                    '<div class="col-md-6">' +
                        '<label class="form-label" style="font-size:0.78rem;">Min Date</label>' +
                        '<input type="date" class="form-control form-control-sm cv-min-date" value="' + escapeHtml(existing.min_date || '') + '">' +
                    '</div>' +
                    '<div class="col-md-6">' +
                        '<label class="form-label" style="font-size:0.78rem;">Max Date</label>' +
                        '<input type="date" class="form-control form-control-sm cv-max-date" value="' + escapeHtml(existing.max_date || '') + '">' +
                    '</div>' +
                '</div>';
        }

        fieldsHost.innerHTML = html;

        // Wire pattern/message preset behaviour for text/textarea blocks.
        var patternPreset = fieldsHost.querySelector('.cv-pattern-preset');
        var patternInput = fieldsHost.querySelector('.cv-pattern');
        var messagePreset = fieldsHost.querySelector('.cv-message-preset');
        var messageInput = fieldsHost.querySelector('.cv-pattern-message');
        if (patternPreset) {
            patternPreset.addEventListener('change', function () {
                var opt = this.options[this.selectedIndex];
                if (!opt || !opt.value) return;
                if (opt.value === 'custom') {
                    patternInput.value = '';
                    messageInput.value = '';
                    patternInput.focus();
                    return;
                }
                patternInput.value = opt.dataset.pattern || '';
                if (!messageInput.value) messageInput.value = opt.dataset.message || '';
            });
        }
        if (messagePreset) {
            messagePreset.addEventListener('change', function () {
                if (this.value) messageInput.value = this.value;
            });
        }
    }

    // Read the visible column-validation inputs back into a plain object.
    function serializeColumnValidation(card, colType) {
        var rules = {};
        var fields = card.querySelector('.col-validation-fields');
        if (!fields) return rules;

        if (colType === 'text' || colType === 'textarea') {
            var pattern = (fields.querySelector('.cv-pattern') || {}).value || '';
            if (pattern.trim()) rules.pattern = pattern.trim();
            var msg = (fields.querySelector('.cv-pattern-message') || {}).value || '';
            if (msg.trim()) rules.pattern_message = msg.trim();
            var minL = (fields.querySelector('.cv-min-length') || {}).value;
            var maxL = (fields.querySelector('.cv-max-length') || {}).value;
            if (minL !== '' && minL != null) rules.min_length = parseInt(minL, 10);
            if (maxL !== '' && maxL != null) rules.max_length = parseInt(maxL, 10);
        } else if (colType === 'number') {
            var mn = (fields.querySelector('.cv-min') || {}).value;
            var mx = (fields.querySelector('.cv-max') || {}).value;
            if (mn !== '' && mn != null) rules.min = Number(mn);
            if (mx !== '' && mx != null) rules.max = Number(mx);
        } else if (colType === 'date') {
            var ap = fields.querySelector('.cv-allow-past');
            var af = fields.querySelector('.cv-allow-future');
            var at = fields.querySelector('.cv-allow-today');
            if (ap && !ap.checked) rules.allow_past = false;
            if (af && !af.checked) rules.allow_future = false;
            if (at && !at.checked) rules.allow_today = false;
            var md = (fields.querySelector('.cv-min-date') || {}).value;
            var xd = (fields.querySelector('.cv-max-date') || {}).value;
            if (md) rules.min_date = md;
            if (xd) rules.max_date = xd;
        }
        return rules;
    }

    function slugify(text) {
        return text.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    }

    function createColumnCard(data) {
        data = data || {};
        var idx = columnCounter++;
        var card = document.createElement('div');
        card.className = 'table-column-card';
        card.dataset.idx = idx;

        var colType = data.type || 'text';
        var typesWithSuboptions = ['select', 'checkbox'];
        var showSuboptions = typesWithSuboptions.indexOf(colType) !== -1;
        var suboptionsHtml = '';
        if (data.options && data.options.length) {
            data.options.forEach(function(opt) {
                suboptionsHtml += '<div class="suboption-row">' +
                    '<input type="text" class="form-control form-control-sm subopt-label" placeholder="Label" value="' + (opt.label || '') + '">' +
                    '<input type="text" class="form-control form-control-sm subopt-value" placeholder="Value" value="' + (opt.value || '') + '">' +
                    '<button type="button" class="btn btn-sm btn-outline-danger remove-subopt-btn" title="Remove"><i class="bi bi-x"></i></button>' +
                    '</div>';
            });
        } else {
            suboptionsHtml = '<div class="suboption-row">' +
                '<input type="text" class="form-control form-control-sm subopt-label" placeholder="Label">' +
                '<input type="text" class="form-control form-control-sm subopt-value" placeholder="Value">' +
                '<button type="button" class="btn btn-sm btn-outline-danger remove-subopt-btn" title="Remove"><i class="bi bi-x"></i></button>' +
                '</div>';
        }

        card.innerHTML = '<span class="column-number">Column #' + (idx + 1) + '</span>' +
            '<button type="button" class="btn btn-sm btn-outline-danger remove-column-btn" title="Remove column"><i class="bi bi-trash"></i></button>' +
            '<div class="row g-2 mt-2">' +
                '<div class="col-md-3">' +
                    '<label class="form-label" style="font-size:0.8rem;">Label <span class="text-danger">*</span></label>' +
                    '<input type="text" class="form-control form-control-sm col-label" placeholder="e.g. Name" value="' + (data.label || '') + '">' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label" style="font-size:0.8rem;">Key</label>' +
                    '<input type="text" class="form-control form-control-sm col-key" placeholder="Auto-generated" value="' + (data.key || '') + '">' +
                '</div>' +
                '<div class="col-md-2">' +
                    '<label class="form-label" style="font-size:0.8rem;">Type</label>' +
                    '<select class="form-select form-select-sm col-type">' +
                        '<option value="text"' + (colType === 'text' ? ' selected' : '') + '>Text</option>' +
                        '<option value="number"' + (colType === 'number' ? ' selected' : '') + '>Number</option>' +
                        '<option value="date"' + (colType === 'date' ? ' selected' : '') + '>Date</option>' +
                        '<option value="select"' + (colType === 'select' ? ' selected' : '') + '>Select</option>' +
                        '<option value="checkbox"' + (colType === 'checkbox' ? ' selected' : '') + '>Checkbox</option>' +
                        '<option value="file"' + (colType === 'file' ? ' selected' : '') + '>File</option>' +
                    '</select>' +
                '</div>' +
                '<div class="col-md-2">' +
                    '<label class="form-label" style="font-size:0.8rem;">Placeholder</label>' +
                    '<input type="text" class="form-control form-control-sm col-placeholder" value="' + (data.placeholder || '') + '">' +
                '</div>' +
                '<div class="col-md-2 d-flex align-items-end">' +
                    '<div class="form-check">' +
                        '<input type="checkbox" class="form-check-input col-required"' + (data.required ? ' checked' : '') + '>' +
                        '<label class="form-check-label" style="font-size:0.8rem;">Required</label>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="col-suboptions-wrapper" style="' + (showSuboptions ? '' : 'display:none') + '">' +
                '<div class="table-column-suboptions mt-2">' +
                    '<div class="form-label" style="font-size:0.75rem;font-weight:600;">Select Options</div>' +
                    '<div class="suboptions-list">' + suboptionsHtml + '</div>' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary add-subopt-btn mt-1"><i class="bi bi-plus"></i> Add Option</button>' +
                '</div>' +
            '</div>' +
            '<div class="col-validation-wrapper" style="display:none;">' +
                '<div class="table-column-suboptions mt-2">' +
                    '<div class="form-label" style="font-size:0.75rem;font-weight:600;">Validation Rules</div>' +
                    '<div class="col-validation-fields"></div>' +
                '</div>' +
            '</div>';

        var labelInput = card.querySelector('.col-label');
        var keyInput = card.querySelector('.col-key');
        labelInput.addEventListener('input', function() {
            if (!keyInput.dataset.manual) keyInput.value = slugify(this.value);
        });
        keyInput.addEventListener('input', function() {
            if (this.value) keyInput.dataset.manual = '1'; else delete keyInput.dataset.manual;
        });
        if (data.key) keyInput.dataset.manual = '1';

        card.querySelector('.col-type').addEventListener('change', function() {
            card.querySelector('.col-suboptions-wrapper').style.display =
                typesWithSuboptions.indexOf(this.value) !== -1 ? '' : 'none';
            // Re-render validation fields against the new type. Any values
            // entered for the previous type are intentionally dropped — they
            // wouldn't apply to the new column type anyway.
            renderColumnValidationFields(card, this.value, {});
        });

        // Initial render of validation fields based on the column's type.
        renderColumnValidationFields(card, colType, data.validation || {});

        card.querySelector('.remove-column-btn').addEventListener('click', function() {
            card.remove();
            renumberColumns();
        });

        card.querySelector('.add-subopt-btn').addEventListener('click', function() {
            var row = document.createElement('div');
            row.className = 'suboption-row';
            row.innerHTML = '<input type="text" class="form-control form-control-sm subopt-label" placeholder="Label">' +
                '<input type="text" class="form-control form-control-sm subopt-value" placeholder="Value">' +
                '<button type="button" class="btn btn-sm btn-outline-danger remove-subopt-btn" title="Remove"><i class="bi bi-x"></i></button>';
            card.querySelector('.suboptions-list').appendChild(row);
        });

        card.querySelector('.suboptions-list').addEventListener('click', function(e) {
            var btn = e.target.closest('.remove-subopt-btn');
            if (btn && card.querySelectorAll('.suboption-row').length > 1) btn.closest('.suboption-row').remove();
        });

        tableColumnsList.appendChild(card);
        return card;
    }

    function renumberColumns() {
        tableColumnsList.querySelectorAll('.table-column-card').forEach(function(card, i) {
            card.querySelector('.column-number').textContent = 'Column #' + (i + 1);
        });
    }

    document.getElementById('addTableColumnBtn').addEventListener('click', function() {
        createColumnCard();
    });

    // === Validation Rules: presets + serialization ===
    var vrPatternPreset = document.getElementById('vrPatternPreset');
    var vrPattern = document.getElementById('vrPattern');
    var vrPatternMessage = document.getElementById('vrPatternMessage');
    var vrMessagePreset = document.getElementById('vrMessagePreset');

    vrPatternPreset.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) return;
        if (opt.value === 'custom') {
            vrPattern.value = '';
            vrPatternMessage.value = '';
            vrPattern.focus();
            return;
        }
        vrPattern.value = opt.dataset.pattern || '';
        if (!vrPatternMessage.value) {
            vrPatternMessage.value = opt.dataset.message || '';
        }
    });

    vrMessagePreset.addEventListener('change', function () {
        if (this.value) vrPatternMessage.value = this.value;
    });

    // Pre-fill the validation rules block when editing.
    @if($question && $question->validation_rules)
    (function () {
        var rules = @json($question->validation_rules);
        if (!rules || typeof rules !== 'object') return;
        if (rules.pattern != null) vrPattern.value = rules.pattern;
        if (rules.pattern_message != null) vrPatternMessage.value = rules.pattern_message;
        if (rules.min_length != null) document.getElementById('vrMinLength').value = rules.min_length;
        if (rules.max_length != null) document.getElementById('vrMaxLength').value = rules.max_length;
        if (rules.min != null) document.getElementById('vrMin').value = rules.min;
        if (rules.max != null) document.getElementById('vrMax').value = rules.max;
        if (rules.allow_past === false) document.getElementById('vrAllowPast').checked = false;
        if (rules.allow_future === false) document.getElementById('vrAllowFuture').checked = false;
        if (rules.allow_today === false) document.getElementById('vrAllowToday').checked = false;
        if (rules.min_date) document.getElementById('vrMinDate').value = rules.min_date;
        if (rules.max_date) document.getElementById('vrMaxDate').value = rules.max_date;
    })();
    @endif

    function serializeValidationRules(type) {
        if (typesWithValidation.indexOf(type) === -1) return null;
        var rules = {};

        if (type === 'text' || type === 'textarea') {
            var pattern = vrPattern.value.trim();
            if (pattern) rules.pattern = pattern;
            var msg = vrPatternMessage.value.trim();
            if (msg) rules.pattern_message = msg;
            var minL = document.getElementById('vrMinLength').value;
            var maxL = document.getElementById('vrMaxLength').value;
            if (minL !== '') rules.min_length = parseInt(minL, 10);
            if (maxL !== '') rules.max_length = parseInt(maxL, 10);
        } else if (type === 'number') {
            var min = document.getElementById('vrMin').value;
            var max = document.getElementById('vrMax').value;
            if (min !== '') rules.min = Number(min);
            if (max !== '') rules.max = Number(max);
        } else if (type === 'date') {
            // Only emit the boolean flags when the admin disables the default
            // (allow). Keeps stored JSON minimal.
            if (!document.getElementById('vrAllowPast').checked) rules.allow_past = false;
            if (!document.getElementById('vrAllowFuture').checked) rules.allow_future = false;
            if (!document.getElementById('vrAllowToday').checked) rules.allow_today = false;
            var mind = document.getElementById('vrMinDate').value;
            var maxd = document.getElementById('vrMaxDate').value;
            if (mind) rules.min_date = mind;
            if (maxd) rules.max_date = maxd;
        }
        return rules;
    }

    // === Serialize data on form submit ===
    document.getElementById('questionForm').addEventListener('submit', function() {
        var val = typeSelect.value;

        var vrules = serializeValidationRules(val);
        validationRulesJson.value = (vrules && Object.keys(vrules).length > 0)
            ? JSON.stringify(vrules)
            : '';

        // Serialize options builder for radio/select/multi_select
        if (typesWithOptions.includes(val)) {
            var options = [];
            optionsList.querySelectorAll('.option-row').forEach(function(row) {
                var label = row.querySelector('.opt-label').value.trim();
                var value = row.querySelector('.opt-value').value.trim();
                if (label && value) options.push({ label: label, value: value });
            });
            optionsHidden.value = JSON.stringify(options);
        }

        // Serialize table builder for table type
        if (val === 'table') {
            var columns = [];
            tableColumnsList.querySelectorAll('.table-column-card').forEach(function(card) {
                var col = {
                    label: card.querySelector('.col-label').value.trim(),
                    key: card.querySelector('.col-key').value.trim() || slugify(card.querySelector('.col-label').value.trim()),
                    type: card.querySelector('.col-type').value,
                    required: card.querySelector('.col-required').checked,
                    placeholder: card.querySelector('.col-placeholder').value.trim()
                };
                if (col.type === 'select' || col.type === 'checkbox') {
                    col.options = [];
                    card.querySelectorAll('.suboption-row').forEach(function(row) {
                        var l = row.querySelector('.subopt-label').value.trim();
                        var v = row.querySelector('.subopt-value').value.trim();
                        if (l && v) col.options.push({ label: l, value: v });
                    });
                }
                var colValidation = serializeColumnValidation(card, col.type);
                if (colValidation && Object.keys(colValidation).length > 0) {
                    col.validation = colValidation;
                }
                if (col.label) columns.push(col);
            });
            tableOptionsJson.value = JSON.stringify({
                columns: columns,
                min_rows: parseInt(document.getElementById('tableMinRows').value) || 1,
                max_rows: parseInt(document.getElementById('tableMaxRows').value) || 10,
                allow_add_rows: document.getElementById('tableAllowAddRows').checked
            });
        }
    });

    // === Load existing table data when editing ===
    @if($question && $question->type === 'table' && $question->options)
    (function() {
        var existing = @json($question->options);
        if (existing && existing.columns) {
            existing.columns.forEach(function(col) { createColumnCard(col); });
            document.getElementById('tableMinRows').value = existing.min_rows || 1;
            document.getElementById('tableMaxRows').value = existing.max_rows || 10;
            document.getElementById('tableAllowAddRows').checked = existing.allow_add_rows !== false;
        }
    })();
    @endif

    // === Initialize on page load ===
    toggleOptions();

    if (typeSelect.value === 'table' && tableColumnsList.children.length === 0) {
        createColumnCard();
    }

    // === Type mapping toggle ===
    document.querySelectorAll('.type-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var idx = this.dataset.idx;
            var card = this.closest('.type-mapping-card');
            var options = card.querySelector('.mapping-options-' + idx);
            var subcats = card.querySelector('.subcategories-' + idx);
            var inputs = card.querySelectorAll('.mapping-input-' + idx);

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

        if (toggle.checked) {
            toggle.closest('.type-mapping-card').classList.add('active');
        }
    });
</script>
@endpush
