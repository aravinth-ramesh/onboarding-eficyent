@extends('admin.layouts.app')

@section('title', 'Create New Question')

@section('actions')
    <a href="{{ route('admin.user-onboardings.show', $userOnboarding) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">Assign New Question to {{ $userOnboarding->user->name ?? $userOnboarding->user->email }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.user-onboardings.store-question', $userOnboarding) }}" id="newQuestionForm">
                    @csrf

                    <div class="mb-3">
                        <label for="label" class="form-label">Question Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('label') is-invalid @enderror" id="label" name="label"
                            value="{{ old('label') }}" required placeholder="Enter the question text">
                        @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                            rows="2" placeholder="Optional description or help text for the user">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="type" class="form-label">Question Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="">-- Select Type --</option>
                                <option value="text" {{ old('type') === 'text' ? 'selected' : '' }}>Text</option>
                                <option value="textarea" {{ old('type') === 'textarea' ? 'selected' : '' }}>Textarea</option>
                                <option value="number" {{ old('type') === 'number' ? 'selected' : '' }}>Number</option>
                                <option value="date" {{ old('type') === 'date' ? 'selected' : '' }}>Date</option>
                                <option value="radio" {{ old('type') === 'radio' ? 'selected' : '' }}>Radio</option>
                                <option value="select" {{ old('type') === 'select' ? 'selected' : '' }}>Select Dropdown</option>
                                <option value="multi_select" {{ old('type') === 'multi_select' ? 'selected' : '' }}>Multi Select</option>
                                <option value="file" {{ old('type') === 'file' ? 'selected' : '' }}>File Upload</option>
                                <option value="table" {{ old('type') === 'table' ? 'selected' : '' }}>Table</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-check mt-2">
                                <input type="hidden" name="is_required" value="0">
                                <input type="checkbox" class="form-check-input" id="is_required" name="is_required" value="1"
                                    {{ old('is_required', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_required">Required</label>
                            </div>
                        </div>
                    </div>

                    {{-- Options Builder (for radio, select, multi_select) --}}
                    <div class="mb-3" id="optionsSection" style="display: none;">
                        <label class="form-label">Options <span class="text-danger">*</span></label>
                        <div id="optionsList">
                            <div class="input-group mb-2 option-row">
                                <input type="text" class="form-control option-label" placeholder="Label">
                                <input type="text" class="form-control option-value" placeholder="Value">
                                <button type="button" class="btn btn-outline-danger remove-option-btn" title="Remove">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="addOptionBtn">
                            <i class="bi bi-plus"></i> Add Option
                        </button>
                        <input type="hidden" name="options" id="optionsJson">
                    </div>

                    {{-- Table Column Builder (for type=table) --}}
                    <div class="mb-3" id="tableSection" style="display: none;">
                        <label class="form-label fw-semibold">Table Columns</label>
                        <div class="form-text mb-2">Define the columns for this table question.</div>

                        <div id="tableColumnsList"></div>

                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addTableColumnBtn">
                            <i class="bi bi-plus-circle"></i> Add Column
                        </button>

                        <hr class="my-3">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" style="font-size: 0.85rem;">Min Rows</label>
                                <input type="number" class="form-control form-control-sm" id="tableMinRows" min="1" max="100" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size: 0.85rem;">Max Rows</label>
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
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="placeholder" class="form-label">Placeholder</label>
                            <input type="text" class="form-control" id="placeholder" name="placeholder"
                                value="{{ old('placeholder') }}" placeholder="Input placeholder text">
                        </div>
                        <div class="col-md-6">
                            <label for="help_text" class="form-label">Help Text</label>
                            <input type="text" class="form-control" id="help_text" name="help_text"
                                value="{{ old('help_text') }}" placeholder="Additional help text">
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="message" class="form-label">Message to User <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('message') is-invalid @enderror" id="message" name="message"
                            rows="3" required placeholder="Explain why this question is being assigned...">{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="send_email" name="send_email" value="1">
                        <label class="form-check-label" for="send_email">Also send email notification</label>
                    </div>

                    <div id="emailFields" style="display: none;">
                        <div class="mb-3">
                            <label for="email_subject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control" id="email_subject" name="email_subject">
                        </div>
                        <div class="mb-3">
                            <label for="email_body" class="form-label">Email Body</label>
                            <textarea class="form-control" id="email_body" name="email_body" rows="5"></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.user-onboardings.show', $userOnboarding) }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Assign Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .table-column-card {
        border: 1px solid #e1e5eb;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        background: #fafbfc;
        position: relative;
    }
    .table-column-card .column-number {
        position: absolute; top: 8px; left: 12px;
        font-size: 0.7rem; font-weight: 700; color: #6c757d; text-transform: uppercase;
    }
    .table-column-card .remove-column-btn {
        position: absolute; top: 6px; right: 8px;
    }
    .table-column-suboptions {
        margin-top: 8px; padding: 8px; background: #fff; border: 1px solid #e9ecef; border-radius: 4px;
    }
    .table-column-suboptions .suboption-row {
        display: flex; gap: 6px; margin-bottom: 4px;
    }
</style>
@endpush

@push('scripts')
<script>
    var optionsTypes = ['radio', 'select', 'multi_select'];
    var typeSelect = document.getElementById('type');

    // Show/hide options section and table section based on type
    typeSelect.addEventListener('change', function () {
        var val = this.value;
        document.getElementById('optionsSection').style.display = optionsTypes.includes(val) ? 'block' : 'none';
        document.getElementById('tableSection').style.display = val === 'table' ? 'block' : 'none';
    });

    // Add option row
    document.getElementById('addOptionBtn').addEventListener('click', function () {
        var row = document.createElement('div');
        row.className = 'input-group mb-2 option-row';
        row.innerHTML = '<input type="text" class="form-control option-label" placeholder="Label">' +
            '<input type="text" class="form-control option-value" placeholder="Value">' +
            '<button type="button" class="btn btn-outline-danger remove-option-btn" title="Remove"><i class="bi bi-x"></i></button>';
        document.getElementById('optionsList').appendChild(row);
    });

    // Remove option row
    document.getElementById('optionsList').addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-option-btn');
        if (btn) {
            var rows = document.querySelectorAll('.option-row');
            if (rows.length > 1) {
                btn.closest('.option-row').remove();
            }
        }
    });

    // --- Table Column Builder ---
    var tableColumnsList = document.getElementById('tableColumnsList');
    var columnCounter = 0;

    function slugify(text) {
        return text.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    }

    function createTableColumn(data) {
        data = data || {};
        var idx = columnCounter++;
        var card = document.createElement('div');
        card.className = 'table-column-card';

        var colType = data.type || 'text';
        var showSubopts = colType === 'select';
        var subHtml = '<div class="suboption-row">' +
            '<input type="text" class="form-control form-control-sm subopt-label" placeholder="Label">' +
            '<input type="text" class="form-control form-control-sm subopt-value" placeholder="Value">' +
            '<button type="button" class="btn btn-sm btn-outline-danger remove-subopt-btn"><i class="bi bi-x"></i></button></div>';

        card.innerHTML = '<span class="column-number">Column #' + (idx + 1) + '</span>' +
            '<button type="button" class="btn btn-sm btn-outline-danger remove-column-btn"><i class="bi bi-trash"></i></button>' +
            '<div class="row g-2 mt-2">' +
                '<div class="col-md-3"><label class="form-label" style="font-size:0.8rem;">Label *</label>' +
                    '<input type="text" class="form-control form-control-sm col-label" placeholder="e.g. Name" value="' + (data.label || '') + '"></div>' +
                '<div class="col-md-3"><label class="form-label" style="font-size:0.8rem;">Key</label>' +
                    '<input type="text" class="form-control form-control-sm col-key" placeholder="Auto-generated" value="' + (data.key || '') + '"></div>' +
                '<div class="col-md-2"><label class="form-label" style="font-size:0.8rem;">Type</label>' +
                    '<select class="form-select form-select-sm col-type">' +
                        '<option value="text"' + (colType === 'text' ? ' selected' : '') + '>Text</option>' +
                        '<option value="number"' + (colType === 'number' ? ' selected' : '') + '>Number</option>' +
                        '<option value="date"' + (colType === 'date' ? ' selected' : '') + '>Date</option>' +
                        '<option value="select"' + (colType === 'select' ? ' selected' : '') + '>Select</option>' +
                    '</select></div>' +
                '<div class="col-md-2"><label class="form-label" style="font-size:0.8rem;">Placeholder</label>' +
                    '<input type="text" class="form-control form-control-sm col-placeholder" value="' + (data.placeholder || '') + '"></div>' +
                '<div class="col-md-2 d-flex align-items-end"><div class="form-check">' +
                    '<input type="checkbox" class="form-check-input col-required"' + (data.required ? ' checked' : '') + '>' +
                    '<label class="form-check-label" style="font-size:0.8rem;">Required</label></div></div>' +
            '</div>' +
            '<div class="col-suboptions-wrapper" style="' + (showSubopts ? '' : 'display:none') + '">' +
                '<div class="table-column-suboptions mt-2">' +
                    '<div class="form-label" style="font-size:0.75rem;font-weight:600;">Select Options</div>' +
                    '<div class="suboptions-list">' + subHtml + '</div>' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary add-subopt-btn mt-1"><i class="bi bi-plus"></i> Add Option</button>' +
                '</div></div>';

        // Auto-generate key from label
        var labelEl = card.querySelector('.col-label');
        var keyEl = card.querySelector('.col-key');
        labelEl.addEventListener('input', function() {
            if (!keyEl.dataset.manual) keyEl.value = slugify(this.value);
        });
        keyEl.addEventListener('input', function() {
            if (this.value) keyEl.dataset.manual = '1'; else delete keyEl.dataset.manual;
        });
        if (data.key) keyEl.dataset.manual = '1';

        // Toggle suboptions
        card.querySelector('.col-type').addEventListener('change', function() {
            card.querySelector('.col-suboptions-wrapper').style.display = this.value === 'select' ? '' : 'none';
        });

        // Remove column
        card.querySelector('.remove-column-btn').addEventListener('click', function() {
            card.remove();
            var cards = tableColumnsList.querySelectorAll('.table-column-card');
            cards.forEach(function(c, i) { c.querySelector('.column-number').textContent = 'Column #' + (i + 1); });
        });

        // Add suboption
        card.querySelector('.add-subopt-btn').addEventListener('click', function() {
            var row = document.createElement('div');
            row.className = 'suboption-row';
            row.innerHTML = '<input type="text" class="form-control form-control-sm subopt-label" placeholder="Label">' +
                '<input type="text" class="form-control form-control-sm subopt-value" placeholder="Value">' +
                '<button type="button" class="btn btn-sm btn-outline-danger remove-subopt-btn"><i class="bi bi-x"></i></button>';
            card.querySelector('.suboptions-list').appendChild(row);
        });

        // Remove suboption
        card.querySelector('.suboptions-list').addEventListener('click', function(e) {
            var btn = e.target.closest('.remove-subopt-btn');
            if (btn && card.querySelectorAll('.suboption-row').length > 1) btn.closest('.suboption-row').remove();
        });

        tableColumnsList.appendChild(card);
    }

    document.getElementById('addTableColumnBtn').addEventListener('click', function() {
        createTableColumn();
    });

    // Build JSON before submit
    document.getElementById('newQuestionForm').addEventListener('submit', function () {
        var type = typeSelect.value;
        if (optionsTypes.includes(type)) {
            var options = [];
            document.querySelectorAll('.option-row').forEach(function (row) {
                var label = row.querySelector('.option-label').value.trim();
                var value = row.querySelector('.option-value').value.trim();
                if (label && value) options.push({ label: label, value: value });
            });
            document.getElementById('optionsJson').value = JSON.stringify(options);
        } else if (type === 'table') {
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
            document.getElementById('tableOptionsJson').value = JSON.stringify({
                columns: columns,
                min_rows: parseInt(document.getElementById('tableMinRows').value) || 1,
                max_rows: parseInt(document.getElementById('tableMaxRows').value) || 10,
                allow_add_rows: document.getElementById('tableAllowAddRows').checked
            });
        }
    });

    // Toggle email fields
    document.getElementById('send_email').addEventListener('change', function () {
        document.getElementById('emailFields').style.display = this.checked ? 'block' : 'none';
        if (this.checked) {
            var label = document.getElementById('label').value || 'your assigned question';
            document.getElementById('email_subject').value = 'New Question Assigned to You - ' + label;
            document.getElementById('email_body').value = 'Hello,\n\nA new question has been assigned to you that requires your response.\n\nQuestion: ' + label + '\n\nPlease log in to your account to provide your answer.\n\nThank you,\nEficyent Team';
        }
    });

    // Trigger initial state
    if (typeSelect.value) {
        typeSelect.dispatchEvent(new Event('change'));
    }
</script>
@endpush
