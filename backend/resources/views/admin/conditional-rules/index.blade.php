@extends('admin.layouts.app')

@section('title', 'Conditional Rules')

@section('actions')
    <a href="{{ route('admin.conditional-rules.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> Add Rule
    </a>
@endsection

@section('content')
{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.conditional-rules.index') }}" class="d-flex align-items-center gap-3">
            <label class="form-label mb-0 fw-semibold" style="white-space: nowrap;">Filter by Question:</label>
            <select name="question_id" class="form-select form-select-sm select2-enable" style="max-width: 400px;" onchange="this.form.submit()" data-placeholder="All Questions">
                <option value="">All Questions</option>
                @foreach($questions as $q)
                    <option value="{{ $q->id }}" {{ request('question_id') == $q->id ? 'selected' : '' }}>
                        [{{ $q->group->name ?? 'N/A' }}] {{ Str::limit($q->label, 60) }}
                    </option>
                @endforeach
            </select>
            @if(request('question_id'))
                <a href="{{ route('admin.conditional-rules.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
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
                        <th>Target Question</th>
                        <th>Parent Question</th>
                        <th>Comparison</th>
                        <th>Trigger Value</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr>
                            <td class="fw-semibold">
                                <span class="badge bg-light text-dark mb-1">{{ $rule->question->group->name ?? 'N/A' }}</span><br>
                                {{ Str::limit($rule->question->label ?? 'N/A', 40) }}
                            </td>
                            <td>
                                <span class="badge bg-light text-dark mb-1">{{ $rule->parentQuestion->group->name ?? 'N/A' }}</span><br>
                                {{ Str::limit($rule->parentQuestion->label ?? 'N/A', 40) }}
                            </td>
                            <td><code>{{ $rule->comparison_type }}</code></td>
                            <td>{{ Str::limit($rule->trigger_value ?? '-', 30) }}</td>
                            <td>
                                <span class="badge {{ $rule->action === 'show' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ ucfirst($rule->action) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $rule->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="actions-col">
                                <a href="{{ route('admin.conditional-rules.edit', $rule) }}" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.conditional-rules.destroy', $rule) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Are you sure?')">
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
                            <td colspan="7" class="text-center text-muted py-4">No conditional rules found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($rules->hasPages())
        <div class="card-footer">
            {{ $rules->links() }}
        </div>
    @endif
</div>
@endsection
