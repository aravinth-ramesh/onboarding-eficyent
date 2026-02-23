@extends('admin.layouts.app')

@section('title', 'Question Groups')

@section('actions')
    <a href="{{ route('admin.question-groups.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> Add Group
    </a>
@endsection

@section('content')
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        <tr>
                            <td>{{ $group->order }}</td>
                            <td class="fw-semibold">{{ $group->name }}</td>
                            <td><code>{{ $group->slug }}</code></td>
                            <td>
                                <a href="{{ route('admin.questions.index', ['group_id' => $group->id]) }}" class="text-decoration-none">
                                    {{ $group->questions_count }} questions
                                </a>
                            </td>
                            <td>
                                <span class="badge {{ $group->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $group->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="actions-col">
                                <a href="{{ route('admin.question-groups.edit', $group) }}" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.question-groups.destroy', $group) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Are you sure? This will affect all questions in this group.')">
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
                            <td colspan="6" class="text-center text-muted py-4">No question groups found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($groups->hasPages())
        <div class="card-footer">
            {{ $groups->links() }}
        </div>
    @endif
</div>
@endsection
