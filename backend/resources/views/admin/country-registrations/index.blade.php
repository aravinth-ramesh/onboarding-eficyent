@extends('admin.layouts.app')

@section('title', 'Country Registrations')

@section('actions')
    <a href="{{ route('admin.country-registrations.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> Add Field
    </a>
@endsection

@php
    $label = function ($code) use ($countryNames) {
        return $code === '*' ? 'Default (all other countries)' : ($countryNames[$code] ?? $code);
    };
    $appliesLabels = ['both' => 'FI & Corporate', 'fi' => 'Financial Institution', 'corporate' => 'Corporate'];
@endphp

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold mb-1" style="font-size: .8rem;">Filter by country</label>
                <select name="country" class="form-select" onchange="this.form.submit()">
                    <option value="">All countries</option>
                    @foreach($usedCodes as $code)
                        <option value="{{ $code }}" {{ $filter === $code ? 'selected' : '' }}>{{ $label($code) }}</option>
                    @endforeach
                </select>
            </div>
            @if($filter)
                <div class="col-auto">
                    <a href="{{ route('admin.country-registrations.index') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Country</th>
                        <th>Field</th>
                        <th>Applies To</th>
                        <th>Required</th>
                        <th>Pattern</th>
                        <th>Status</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registrations as $reg)
                        <tr>
                            <td class="fw-semibold">
                                {{ $label($reg->country_code) }}
                                <span class="text-muted">({{ $reg->country_code }})</span>
                            </td>
                            <td>
                                {{ $reg->label }}
                                <div><code>{{ $reg->field_key }}</code></div>
                            </td>
                            <td><span class="badge badge-in-progress">{{ $appliesLabels[$reg->applies_to] ?? $reg->applies_to }}</span></td>
                            <td>{{ $reg->required ? 'Yes' : 'No' }}</td>
                            <td>
                                @if($reg->pattern)
                                    <code style="font-size: .75rem;">{{ Str::limit($reg->pattern, 28) }}</code>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $reg->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $reg->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="actions-col">
                                <a href="{{ route('admin.country-registrations.edit', $reg) }}" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.country-registrations.destroy', $reg) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Delete this registration field?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-action">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No registration fields found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($registrations->hasPages())
        <div class="card-footer">
            {{ $registrations->links() }}
        </div>
    @endif
</div>
@endsection
