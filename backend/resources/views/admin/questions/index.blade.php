@extends('admin.layouts.app')

@section('title', 'Questions')

@section('actions')
    <a href="{{ route('admin.questions.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> Add Question
    </a>
@endsection

@section('content')
{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.questions.index') }}" class="d-flex align-items-center gap-3">
            <label class="form-label mb-0 fw-semibold" style="white-space: nowrap;">Filter by Group:</label>
            <select name="group_id" class="form-select form-select-sm select2-enable" style="max-width: 300px;" onchange="this.form.submit()" data-placeholder="All Groups">
                <option value="">All Groups</option>
                @foreach($groups as $group)
                    <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                        {{ $group->name }}
                    </option>
                @endforeach
            </select>
            @if(request('group_id'))
                <a href="{{ route('admin.questions.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
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
                        <th>Order</th>
                        <th>Label</th>
                        <th>Group</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Status</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($questions as $question)
                        <tr>
                            <td>{{ $question->order }}</td>
                            <td class="fw-semibold">{{ Str::limit($question->label, 50) }}</td>
                            <td>
                                <span class="badge bg-light text-dark">{{ $question->group->name ?? 'N/A' }}</span>
                            </td>
                            <td><code>{{ $question->type }}</code></td>
                            <td>{{ $question->is_required ? 'Yes' : 'No' }}</td>
                            <td>
                                <span class="badge {{ $question->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $question->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="actions-col">
                                <a href="{{ route('admin.questions.edit', $question) }}" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.questions.destroy', $question) }}" method="POST" class="d-inline"
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
                            <td colspan="7" class="text-center text-muted py-4">No questions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($questions->hasPages())
        <div class="card-footer">
            {{ $questions->links() }}
        </div>
    @endif
</div>
@endsection
