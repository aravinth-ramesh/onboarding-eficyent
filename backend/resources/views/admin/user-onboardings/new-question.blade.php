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

@push('scripts')
<script>
    var optionsTypes = ['radio', 'select', 'multi_select'];

    // Show/hide options section based on type
    document.getElementById('type').addEventListener('change', function () {
        var show = optionsTypes.includes(this.value);
        document.getElementById('optionsSection').style.display = show ? 'block' : 'none';
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

    // Build options JSON before submit
    document.getElementById('newQuestionForm').addEventListener('submit', function () {
        var type = document.getElementById('type').value;
        if (optionsTypes.includes(type)) {
            var options = [];
            document.querySelectorAll('.option-row').forEach(function (row) {
                var label = row.querySelector('.option-label').value.trim();
                var value = row.querySelector('.option-value').value.trim();
                if (label && value) {
                    options.push({ label: label, value: value });
                }
            });
            document.getElementById('optionsJson').value = JSON.stringify(options);
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
    if (document.getElementById('type').value) {
        document.getElementById('type').dispatchEvent(new Event('change'));
    }
</script>
@endpush
