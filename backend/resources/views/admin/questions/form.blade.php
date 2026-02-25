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
                            rows="2">{{ old('description', $question?->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select select2-enable @error('type') is-invalid @enderror" required id="questionType" data-placeholder="Select Type">
                                @foreach(['text', 'textarea', 'number', 'date', 'select', 'multi_select', 'radio', 'file', 'table'] as $type)
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
                            value="{{ old('help_text', $question?->help_text) }}">
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
        border-color: #2e86de;
        background: #f8fbff;
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
    var typesWithOptions = ['select', 'multi_select', 'radio'];

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
        var showSuboptions = colType === 'select';
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
            card.querySelector('.col-suboptions-wrapper').style.display = this.value === 'select' ? '' : 'none';
        });

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

    // === Serialize data on form submit ===
    document.getElementById('questionForm').addEventListener('submit', function() {
        var val = typeSelect.value;

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
                if (col.type === 'select') {
                    col.options = [];
                    card.querySelectorAll('.suboption-row').forEach(function(row) {
                        var l = row.querySelector('.subopt-label').value.trim();
                        var v = row.querySelector('.subopt-value').value.trim();
                        if (l && v) col.options.push({ label: l, value: v });
                    });
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
