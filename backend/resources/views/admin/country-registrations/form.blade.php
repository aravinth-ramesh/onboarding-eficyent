@extends('admin.layouts.app')

@section('title', $registration ? 'Edit Registration Field' : 'Add Registration Field')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST"
                    action="{{ $registration ? route('admin.country-registrations.update', $registration) : route('admin.country-registrations.store') }}">
                    @csrf
                    @if($registration) @method('PUT') @endif

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Country <span class="text-danger">*</span></label>
                            <select name="country_code" class="form-select select2-enable @error('country_code') is-invalid @enderror" required data-placeholder="Select country">
                                <option value=""></option>
                                @foreach($countryOptions as $code => $name)
                                    <option value="{{ $code }}" {{ old('country_code', $registration?->country_code) === $code ? 'selected' : '' }}>
                                        {{ $name }}{{ $code !== '*' ? " ($code)" : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Pick "Default" to apply a field to every country without its own override.</div>
                            @error('country_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Applies To <span class="text-danger">*</span></label>
                            <select name="applies_to" class="form-select @error('applies_to') is-invalid @enderror" required>
                                @foreach(['both' => 'FI & Corporate', 'fi' => 'Financial Institution only', 'corporate' => 'Corporate only'] as $val => $text)
                                    <option value="{{ $val }}" {{ old('applies_to', $registration?->applies_to ?? 'both') === $val ? 'selected' : '' }}>{{ $text }}</option>
                                @endforeach
                            </select>
                            @error('applies_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Field Key <span class="text-danger">*</span></label>
                            <input type="text" name="field_key" class="form-control @error('field_key') is-invalid @enderror"
                                value="{{ old('field_key', $registration?->field_key) }}" placeholder="gstin" required>
                            <div class="form-text">Lowercase letters, numbers and underscores. Stored with the answer.</div>
                            @error('field_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Label <span class="text-danger">*</span></label>
                            <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                                value="{{ old('label', $registration?->label) }}" placeholder="GSTIN" required>
                            <div class="form-text">Shown to the client.</div>
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Help Text</label>
                        <textarea name="help" rows="2" class="form-control @error('help') is-invalid @enderror"
                            placeholder="Explain what this identifier is and where to find it.">{{ old('help', $registration?->help) }}</textarea>
                        <div class="form-text"><i class="bi bi-info-circle"></i> Shown in the info tooltip next to the field.</div>
                        @error('help')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Validation Pattern (regex)</label>
                            <input type="text" name="pattern" class="form-control @error('pattern') is-invalid @enderror"
                                value="{{ old('pattern', $registration?->pattern) }}" placeholder="^[A-Z]{5}[0-9]{4}[A-Z]$">
                            <div class="form-text">Optional. Anchored automatically; leave blank to skip format checks.</div>
                            @error('pattern')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Pattern Error Message</label>
                            <input type="text" name="pattern_message" class="form-control @error('pattern_message') is-invalid @enderror"
                                value="{{ old('pattern_message', $registration?->pattern_message) }}" placeholder="Enter a valid 10-character PAN.">
                            @error('pattern_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Placeholder</label>
                            <input type="text" name="placeholder" class="form-control @error('placeholder') is-invalid @enderror"
                                value="{{ old('placeholder', $registration?->placeholder) }}" placeholder="AAAAA0000A">
                            @error('placeholder')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Order</label>
                            <input type="number" name="order" min="0" class="form-control @error('order') is-invalid @enderror"
                                value="{{ old('order', $registration?->order ?? 0) }}">
                            <div class="form-text">Lower numbers appear first.</div>
                            @error('order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex gap-4 mb-3">
                        <div class="form-check">
                            <input type="hidden" name="required" value="0">
                            <input type="checkbox" name="required" value="1" class="form-check-input" id="required"
                                {{ old('required', $registration?->required ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="required">Required</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                                {{ old('is_active', $registration?->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            {{ $registration ? 'Update Field' : 'Create Field' }}
                        </button>
                        <a href="{{ route('admin.country-registrations.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
