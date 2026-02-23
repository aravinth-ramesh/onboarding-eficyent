@extends('admin.layouts.app')

@section('title', 'User Types')

@section('actions')
    <a href="{{ route('admin.user-types.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> Add User Type
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
                        <th>Subcategories</th>
                        <th>Status</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($userTypes as $type)
                        <tr>
                            <td>{{ $type->order }}</td>
                            <td class="fw-semibold">{{ $type->name }}</td>
                            <td><code>{{ $type->slug }}</code></td>
                            <td>
                                @if($type->has_subcategories)
                                    <a href="{{ route('admin.user-types.subcategories.index', $type) }}" class="text-decoration-none">
                                        {{ $type->subcategories_count }} subcategories
                                    </a>
                                @else
                                    <span class="text-muted">None</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $type->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $type->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="actions-col">
                                <a href="{{ route('admin.user-types.edit', $type) }}" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.user-types.destroy', $type) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Are you sure you want to delete this user type?')">
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
                            <td colspan="6" class="text-center text-muted py-4">No user types found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($userTypes->hasPages())
        <div class="card-footer">
            {{ $userTypes->links() }}
        </div>
    @endif
</div>
@endsection
